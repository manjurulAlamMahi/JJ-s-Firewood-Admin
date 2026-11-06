<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\OrderAuditLog;

/**
 * Listens for changes to the Order model and writes an entry
 * to order_audit_logs for every status / payment / delivery date /
 * total / notes change. Pure side-effect observer — never modifies orders.
 *
 * Registered in AppServiceProvider::boot().
 */
class OrderObserver
{
    /**
     * Columns we care about. Any change to these triggers an audit log entry.
     */
    protected array $watched = [
        'order_status',
        'payment_status',
        'preferred_delivery_date',
        'total',
        'notes',
    ];

    /**
     * Friendly action keys per column (for filtering the timeline UI).
     */
    protected array $actionMap = [
        'order_status'            => 'status_changed',
        'payment_status'          => 'payment_status_changed',
        'preferred_delivery_date' => 'delivery_date_changed',
        'total'                   => 'total_changed',
        'notes'                   => 'notes_updated',
    ];

    /**
     * Fired BEFORE the order is saved.
     *
     * Auto-clears needs_action_dismissed_at whenever a "material" field
     * is changing — i.e. anything that would normally cause an admin to
     * want the order back on their radar. This gives the "comes back if
     * something changes" behaviour for the dismiss feature.
     *
     * Material fields:
     *   - payment_status (e.g. customer just paid)
     *   - order_status   (e.g. moved to processing, cancelled, etc.)
     *   - preferred_delivery_date (someone rescheduled)
     *   - delivery_date_confirmed_at (admin confirmed via Setmore/manual)
     *
     * We only clear; we never set this column from inside the observer.
     */
    public function updating(Order $order): void
    {
        // Skip if column doesn't exist yet (pre-migration deploys)
        if (!\Schema::hasColumn('orders', 'needs_action_dismissed_at')) {
            return;
        }
        // Skip if it's already null — nothing to clear
        if (is_null($order->needs_action_dismissed_at)) {
            return;
        }

        $material = [
            'payment_status',
            'order_status',
            'preferred_delivery_date',
            'delivery_date_confirmed_at',
        ];

        // If the same save is ALSO trying to set the dismiss flag explicitly
        // (i.e. admin just clicked Dismiss), don't clear it — respect the
        // admin's action.
        if (array_key_exists('needs_action_dismissed_at', $order->getDirty())) {
            return;
        }

        // Otherwise: if any material field is dirty, the order has changed
        // state and should reappear in Needs Action — clear the flag.
        foreach ($material as $col) {
            if (!\Schema::hasColumn('orders', $col)) continue;
            if (array_key_exists($col, $order->getDirty())) {
                $order->needs_action_dismissed_at = null;
                return;
            }
        }
    }

    /**
     * Fired after the order is updated.
     */
    public function updated(Order $order): void
    {
        // Pull the changed attributes (only the watched ones)
        $changed = array_intersect_key(
            $order->getChanges(),
            array_flip($this->watched)
        );

        if (empty($changed)) {
            return;
        }

        foreach ($changed as $column => $newValue) {
            $oldValue = $order->getOriginal($column);

            // Skip if the change is purely whitespace or formatting
            if ((string) $oldValue === (string) $newValue) {
                continue;
            }

            $action = $this->actionMap[$column] ?? 'updated';

            try {
                OrderAuditLog::record(
                    orderId: $order->id,
                    action: $action,
                    description: $this->describe($column, $oldValue, $newValue),
                    metadata: [
                        'column'  => $column,
                        'old'     => $this->stringify($oldValue),
                        'new'     => $this->stringify($newValue),
                    ],
                );
            } catch (\Throwable $e) {
                // Audit logging must never break order saves.
                logger()->warning('OrderObserver: failed to write audit log', [
                    'order_id' => $order->id,
                    'column'   => $column,
                    'error'    => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Fired after the order is deleted.
     */
    public function deleted(Order $order): void
    {
        try {
            OrderAuditLog::record(
                orderId: $order->id,
                action: 'order_deleted',
                description: "Order #{$order->order_number} deleted",
                metadata: ['order_number' => $order->order_number],
            );
        } catch (\Throwable $e) {
            logger()->warning('OrderObserver: failed to log delete', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build a human-readable description for the timeline.
     */
    protected function describe(string $column, $old, $new): string
    {
        $oldStr = $this->stringify($old) ?: '(empty)';
        $newStr = $this->stringify($new) ?: '(empty)';

        return match ($column) {
            'order_status'            => "Status changed from '{$oldStr}' to '{$newStr}'",
            'payment_status'          => "Payment status changed from '{$oldStr}' to '{$newStr}'",
            'preferred_delivery_date' => "Delivery date changed from {$oldStr} to {$newStr}",
            'total'                   => "Total changed from \${$oldStr} to \${$newStr}",
            'notes'                   => "Order notes updated",
            default                   => "{$column} changed from '{$oldStr}' to '{$newStr}'",
        };
    }

    /**
     * Safely cast any value to a short string for storage / display.
     */
    protected function stringify($value): string
    {
        if (is_null($value)) return '';
        if (is_bool($value)) return $value ? 'true' : 'false';
        if (is_object($value) && method_exists($value, '__toString')) return (string) $value;
        if (is_array($value)) return json_encode($value);
        return (string) $value;
    }
}
