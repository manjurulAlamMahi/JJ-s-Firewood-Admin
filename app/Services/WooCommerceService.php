<?php

namespace App\Services;

use App\Models\OrderTag;
use App\Models\WcCustomer;
use App\Models\WcOrder;
use App\Models\WcOrderAuditLog;
use App\Models\WcOrderItem;
use App\Models\WcOrderRefund;
use App\Models\WoocommerceSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WooCommerceService — writes ONLY to the wc_* tables.
 *
 * Native orders and WC orders are now fully isolated. WC orders never touch
 * the local `orders`, `users`, `shipping_addresses`, `products`, or
 * `order_items` tables. Tags are the one shared resource — `order_tags`
 * definitions are reused via separate pivot tables.
 */
class WooCommerceService
{
    protected WoocommerceSetting $settings;

    public function __construct()
    {
        $this->settings = WoocommerceSetting::singleton();
    }

    public function isConfigured(): bool
    {
        return $this->settings->isConfigured();
    }

    public function getSettings(): WoocommerceSetting
    {
        return $this->settings;
    }

    public function pullOrders(?Carbon $since = null, int $maxOrders = 200): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('WooCommerce is not configured. Save credentials in Settings first.');
        }

        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $imported = 0; $updated = 0; $errors = 0; $failures = [];
        $page = 1; $perPage = 100; $totalProcessed = 0;

        while ($totalProcessed < $maxOrders) {
            $params = [
                'per_page' => $perPage, 'page' => $page,
                'orderby'  => 'modified', 'order' => 'asc', 'status' => 'any',
            ];
            if ($since) {
                $params['modified_after'] = $since->copy()->subMinute()->toIso8601String();
            }

            $response = $this->apiRequest('GET', '/orders', $params);
            if (!$response->successful()) {
                throw new \Exception('WC API error: HTTP ' . $response->status() . ' ' . substr($response->body(), 0, 300));
            }

            $orders = $response->json();
            if (!is_array($orders) || empty($orders)) break;

            foreach ($orders as $wcOrder) {
                try {
                    $result = $this->upsertOrder($wcOrder);
                    if ($result === 'imported') $imported++;
                    elseif ($result === 'updated') $updated++;
                } catch (\Throwable $e) {
                    $errors++;
                    $failures[] = ['wc_id' => $wcOrder['id'] ?? '?', 'error' => $e->getMessage()];
                    Log::error('WC sync upsert failed for #' . ($wcOrder['id'] ?? '?') . ': ' . $e->getMessage());
                }
                $totalProcessed++;
                if ($totalProcessed >= $maxOrders) break 2;
            }

            if (count($orders) < $perPage) break;
            $page++;
        }

        $updateData = [
            'last_sync_result'  => [
                'imported' => $imported, 'updated' => $updated, 'errors' => $errors,
                'completed_at' => now()->toIso8601String(),
                'since' => $since?->toIso8601String(),
            ],
        ];
        if ($errors === 0) {
            $updateData['last_sync_at'] = now();
        }
        $this->settings->update($updateData);

        return [
            'imported' => $imported, 'updated' => $updated, 'errors' => $errors,
            'failures' => array_slice($failures, 0, 20),
        ];
    }

    public function pullSingleOrder(int $wcId): array
    {
        if (!$this->isConfigured()) throw new \Exception('WooCommerce is not configured.');
        $response = $this->apiRequest('GET', '/orders/' . $wcId);
        if (!$response->successful()) throw new \Exception('WC API error: HTTP ' . $response->status());
        return ['result' => $this->upsertOrder($response->json()), 'wc_id' => $wcId];
    }

    protected function upsertOrder(array $wc): string
    {
        return DB::transaction(function () use ($wc) {
            $wcId = (int) $wc['id'];

            $customer = $this->upsertCustomer($wc);

            $billing = $wc['billing'] ?? [];
            $shipping = $wc['shipping'] ?? [];
            $shippingForDisplay = !empty($shipping['address_1']) ? $shipping : $billing;

            $existing = WcOrder::where('woocommerce_id', $wcId)->first();
            $isNew = $existing === null;
            $order = $existing ?? new WcOrder();

            $total      = (float) ($wc['total'] ?? 0);
            $tax        = (float) ($wc['total_tax'] ?? 0);
            $shippingT  = (float) ($wc['shipping_total'] ?? 0);
            $discount   = (float) ($wc['discount_total'] ?? 0);
            $subtotal   = max(0, $total - $tax - $shippingT + $discount);

            $order->fill([
                'woocommerce_id'         => $wcId,
                'woocommerce_number'     => $wc['number'] ?? (string) $wcId,
                'wc_customer_id'         => $customer?->id,

                'wc_status'              => $wc['status'] ?? 'pending',
                'payment_status'         => $this->mapWcStatusToPayment($wc['status'] ?? 'pending'),

                'currency'               => $wc['currency'] ?? 'AUD',
                'subtotal'               => $subtotal,
                'shipping_total'         => $shippingT,
                'tax_total'              => $tax,
                'discount_total'         => $discount,
                'total'                  => $total,
                'refund_total'           => abs((float) ($wc['total_refunded'] ?? 0)),

                'payment_method'         => $wc['payment_method'] ?? null,
                'payment_method_title'   => $wc['payment_method_title'] ?? null,
                'transaction_id'         => $wc['transaction_id'] ?? null,

                'customer_email'         => $billing['email'] ?? null,
                'customer_phone'         => $billing['phone'] ?? null,
                'customer_first_name'    => $billing['first_name'] ?? null,
                'customer_last_name'     => $billing['last_name'] ?? null,

                'billing_address'        => $billing,
                'shipping_address'       => $shipping,

                'suburb'                 => $shippingForDisplay['city'] ?? null,
                'state'                  => $shippingForDisplay['state'] ?? null,
                'postcode'               => $shippingForDisplay['postcode'] ?? null,

                'customer_note'          => $wc['customer_note'] ?? null,
                'preferred_delivery_date' => $this->extractDeliveryDate($wc),

                'wc_date_created'        => isset($wc['date_created']) ? Carbon::parse($wc['date_created']) : null,
                'wc_date_modified'       => isset($wc['date_modified']) ? Carbon::parse($wc['date_modified']) : null,

                'last_synced_at'         => now(),
            ]);

            $order->save();

            // Refresh line items: clear and re-insert. WC is source of truth.
            $order->items()->delete();
            foreach ($wc['line_items'] ?? [] as $item) {
                $qty = (float) ($item['quantity'] ?? 1);
                WcOrderItem::create([
                    'wc_order_id'              => $order->id,
                    'woocommerce_product_id'   => $item['product_id'] ?? null,
                    'woocommerce_variation_id' => $item['variation_id'] ?? null,
                    'sku'                      => $item['sku'] ?? null,
                    'name'                     => $item['name'] ?? 'Item',
                    'quantity'                 => $qty,
                    'price'                    => $qty > 0 ? round((float) ($item['total'] ?? 0) / $qty, 2) : 0,
                    'subtotal'                 => (float) ($item['subtotal'] ?? $item['total'] ?? 0),
                    'total'                    => (float) ($item['total'] ?? 0),
                    'tax'                      => (float) ($item['total_tax'] ?? 0),
                    'meta'                     => $item['meta_data'] ?? null,
                ]);
            }
            $this->refreshCustomerCounters($customer);

            WcOrderAuditLog::record(
                $order->id,
                $isNew ? 'wc_order_imported' : 'wc_order_updated',
                $isNew
                    ? "Imported from WooCommerce (WC#{$wcId}, status: {$wc['status']})"
                    : "Updated from WooCommerce (WC#{$wcId}, status: {$wc['status']})",
                ['wc_id' => $wcId, 'wc_status' => $wc['status'] ?? null, 'wc_total' => $total],
                userId: 0,
            );

            return $isNew ? 'imported' : 'updated';
        });
    }

    protected function upsertCustomer(array $wc): ?WcCustomer
    {
        $billing = $wc['billing'] ?? [];
        $shipping = $wc['shipping'] ?? [];
        $email = trim((string) ($billing['email'] ?? ''));
        $wcCustomerId = (int) ($wc['customer_id'] ?? 0);
        $isGuest = $wcCustomerId === 0;

        $customer = null;
        if (!$isGuest) {
            $customer = WcCustomer::where('woocommerce_id', $wcCustomerId)->first();
        }
        if (!$customer && $email !== '') {
            $customer = WcCustomer::whereRaw('LOWER(email) = ?', [strtolower($email)])->first();
        }

        $payload = [
            'woocommerce_id' => $wcCustomerId ?: null,
            'email'          => $email !== '' ? $email : null,
            'first_name'     => $billing['first_name'] ?? null,
            'last_name'      => $billing['last_name'] ?? null,
            'phone'          => $billing['phone'] ?? null,
            'billing_data'   => $billing,
            'shipping_data'  => $shipping,
            'is_guest'       => $isGuest,
        ];

        if ($customer) {
            $customer->fill($payload);
            $customer->save();
        } else {
            $customer = WcCustomer::create($payload);
        }

        return $customer;
    }

    protected function refreshCustomerCounters(?WcCustomer $customer): void
    {
        if (!$customer) return;
        $stats = WcOrder::where('wc_customer_id', $customer->id)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(CASE WHEN payment_status=\'paid\' THEN total ELSE 0 END), 0) as spent, MAX(wc_date_created) as last_at')
            ->first();
        $customer->update([
            'total_orders'  => (int) ($stats->cnt ?? 0),
            'total_spent'   => (float) ($stats->spent ?? 0),
            'last_order_at' => $stats->last_at,
        ]);
    }

    public function pushOrderUpdate(WcOrder $order): array
    {
        if (!$this->isConfigured()) throw new \Exception('WooCommerce is not configured.');
        if (!$this->settings->sync_enabled) {
            throw new \Exception('Write mode is disabled. Enable "Sync enabled" in Settings to push updates.');
        }

        $payload = ['status' => $order->wc_status];
        if ($order->customer_note !== null) $payload['customer_note'] = $order->customer_note;

        // Push billing/shipping if they're populated on our side
        if (!empty($order->billing_address) && is_array($order->billing_address)) {
            $payload['billing'] = $order->billing_address;
        }
        if (!empty($order->shipping_address) && is_array($order->shipping_address)) {
            $payload['shipping'] = $order->shipping_address;
        }

        $response = $this->apiRequest('PUT', '/orders/' . $order->woocommerce_id, $payload);
        if (!$response->successful()) {
            throw new \Exception('WC API error: HTTP ' . $response->status() . ' ' . substr($response->body(), 0, 300));
        }

        WcOrderAuditLog::record(
            $order->id, 'wc_pushed',
            "Pushed to WooCommerce: status={$order->wc_status}",
            ['pushed_status' => $order->wc_status, 'fields' => array_keys($payload)]
        );

        return ['success' => true, 'wc_id' => $order->woocommerce_id, 'pushed_status' => $order->wc_status];
    }

    /**
     * Wipe local WC data and pull the N most recent orders.
     * Used when the user wants to "start fresh" — typically because they have
     * thousands of legacy orders they don't want sitting in the panel.
     *
     * Sets last_sync_at = now(), so future incremental syncs only pick up
     * orders created or modified after this reset.
     */
    public function resetAndPullLatest(int $count = 100): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('WooCommerce is not configured.');
        }

        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        // Wipe local WC data. FK cascade handles items/notes/audit/tags/refunds.
        DB::transaction(function () {
            // Order matters: delete child rows that don't have CASCADE first,
            // although our FKs do cascade — belt and braces.
            DB::table('wc_order_tag')->delete();
            DB::table('wc_order_refunds')->delete();
            DB::table('wc_order_audit_logs')->delete();
            DB::table('wc_order_notes')->delete();
            DB::table('wc_order_items')->delete();
            DB::table('wc_orders')->delete();
            DB::table('wc_customers')->delete();
        });

        // Pull the N most recent by date desc
        $imported = 0; $errors = 0; $failures = [];
        $perPage  = min(100, $count);
        $page     = 1;
        $remaining = $count;

        while ($remaining > 0) {
            $thisBatch = min($perPage, $remaining);

            $response = $this->apiRequest('GET', '/orders', [
                'per_page' => $thisBatch,
                'page'     => $page,
                'orderby'  => 'date',
                'order'    => 'desc',
                'status'   => 'any',
            ]);

            if (!$response->successful()) {
                throw new \Exception('WC API error: HTTP ' . $response->status() . ' ' . substr($response->body(), 0, 300));
            }

            $orders = $response->json();
            if (!is_array($orders) || empty($orders)) break;

            foreach ($orders as $wcOrder) {
                try {
                    $this->upsertOrder($wcOrder);
                    $imported++;
                } catch (\Throwable $e) {
                    $errors++;
                    $failures[] = ['wc_id' => $wcOrder['id'] ?? '?', 'error' => $e->getMessage()];
                    Log::error('WC reset upsert failed for #' . ($wcOrder['id'] ?? '?') . ': ' . $e->getMessage());
                }
            }

            $remaining -= count($orders);
            if (count($orders) < $thisBatch) break;
            $page++;
        }

        // Watermark: future syncs only pick up orders modified after now
        $this->settings->update([
            'last_sync_at'     => now(),
            'last_sync_result' => [
                'imported'     => $imported,
                'errors'       => $errors,
                'reset'        => true,
                'requested'    => $count,
                'completed_at' => now()->toIso8601String(),
            ],
        ]);

        return [
            'imported' => $imported,
            'errors'   => $errors,
            'failures' => array_slice($failures, 0, 20),
        ];
    }

    public function processRefund(WcOrder $order, float $amount, ?string $reason = null): WcOrderRefund
    {
        if (!$this->isConfigured()) throw new \Exception('WooCommerce is not configured.');
        if (!$this->settings->sync_enabled) {
            throw new \Exception('Write mode is disabled. Enable "Sync enabled" in Settings to issue refunds via the API.');
        }
        if ($amount <= 0) throw new \Exception('Refund amount must be greater than zero.');

        $remaining = $order->refundableAmount();
        if ($amount > $remaining + 0.01) {
            throw new \Exception("Refund amount (\${$amount}) exceeds remaining refundable (\${$remaining}).");
        }

        $payload = [
            'amount'      => number_format($amount, 2, '.', ''),
            'reason'      => $reason ?: 'Refund issued via Order Centre',
            'api_refund'  => true,
            'api_restock' => false,
        ];

        $response = $this->apiRequest('POST', '/orders/' . $order->woocommerce_id . '/refunds', $payload);
        $apiBody = $response->json();

        if (!$response->successful()) {
            WcOrderRefund::create([
                'wc_order_id'           => $order->id,
                'woocommerce_refund_id' => null,
                'amount'                => $amount,
                'reason'                => $reason,
                'refunded_by'           => auth()->id(),
                'refunded_at'           => null,
                'api_response'          => $apiBody,
                'status'                => 'failed',
            ]);

            WcOrderAuditLog::record(
                $order->id, 'wc_refund_failed',
                "Refund of \${$amount} failed: HTTP {$response->status()}",
                ['amount' => $amount, 'reason' => $reason, 'http' => $response->status(), 'body' => $apiBody]
            );

            throw new \Exception('WC refund API error: HTTP ' . $response->status() . ' — ' . ($apiBody['message'] ?? substr($response->body(), 0, 200)));
        }

        $refund = WcOrderRefund::create([
            'wc_order_id'           => $order->id,
            'woocommerce_refund_id' => $apiBody['id'] ?? null,
            'amount'                => $amount,
            'reason'                => $reason,
            'refunded_by'           => auth()->id(),
            'refunded_at'           => now(),
            'api_response'          => $apiBody,
            'status'                => 'completed',
        ]);

        $newRefundTotal = (float) $order->refund_total + $amount;
        $update = ['refund_total' => $newRefundTotal];
        if (abs($newRefundTotal - (float) $order->total) < 0.01) {
            $update['payment_status'] = 'refunded';
        } elseif ($newRefundTotal > 0) {
            $update['payment_status'] = 'partially_refunded';
        }
        $order->update($update);

        WcOrderAuditLog::record(
            $order->id, 'wc_refund_processed',
            "Refunded \${$amount}" . ($reason ? " — {$reason}" : ''),
            ['amount' => $amount, 'reason' => $reason, 'wc_refund_id' => $apiBody['id'] ?? null]
        );

        return $refund;
    }

    public function mapWcStatusToPayment(string $wcStatus): string
    {
        return match ($wcStatus) {
            'completed', 'processing' => 'paid',
            'refunded'                => 'refunded',
            'failed'                  => 'failed',
            default                   => 'pending',
        };
    }

    protected function extractDeliveryDate(array $wc): ?Carbon
    {
        $candidates = [
            'delivery_date', '_delivery_date', 'preferred_delivery_date',
            'wc_od_pickup_date', 'order_delivery_date', 'wcdpd_delivery_date', 'jckwds_date',
        ];
        foreach ($wc['meta_data'] ?? [] as $meta) {
            $key = $meta['key'] ?? '';
            if (in_array($key, $candidates, true) && !empty($meta['value'])) {
                try { return Carbon::parse($meta['value']); }
                catch (\Throwable $e) { continue; }
            }
        }
        return null;
    }

    protected function apiRequest(string $method, string $path, array $data = [])
    {
        $url = rtrim($this->settings->site_url, '/') . '/wp-json/wc/v3' . $path;
        $request = Http::withBasicAuth($this->settings->consumer_key, $this->settings->consumer_secret)
            ->timeout(30)->retry(2, 500);

        return match (strtoupper($method)) {
            'GET'    => $request->get($url, $data),
            'POST'   => $request->post($url, $data),
            'PUT'    => $request->put($url, $data),
            'DELETE' => $request->delete($url, $data),
            default  => throw new \Exception("Unsupported method: {$method}"),
        };
    }
}
