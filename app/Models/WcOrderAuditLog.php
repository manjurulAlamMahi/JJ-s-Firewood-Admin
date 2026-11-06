<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WcOrderAuditLog extends Model
{
    protected $table = 'wc_order_audit_logs';

    protected $fillable = [
        'wc_order_id',
        'user_id',
        'action',
        'description',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(WcOrder::class, 'wc_order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Static helper to record an audit entry, never throws.
     */
    public static function record(int $wcOrderId, string $action, ?string $description = null, ?array $metadata = null, ?int $userId = null): void
    {
        try {
            static::create([
                'wc_order_id' => $wcOrderId,
                'user_id'     => $userId ?? auth()->id(),
                'action'      => $action,
                'description' => $description,
                'metadata'    => $metadata,
            ]);
        } catch (\Throwable $e) {
            \Log::warning("WcOrderAuditLog record failed: " . $e->getMessage());
        }
    }
}
