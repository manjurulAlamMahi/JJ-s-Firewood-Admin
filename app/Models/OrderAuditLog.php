<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderAuditLog extends Model
{
    protected $table = 'order_audit_logs';

    /**
     * Audit logs are append-only. We disable updated_at; created_at only.
     */
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'user_id',
        'action',
        'description',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'order_id'   => 'integer',
        'user_id'    => 'integer',
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Convenience constructor used by the OrderObserver and bulk-action service.
     */
    public static function record(
        int $orderId,
        string $action,
        ?string $description = null,
        array $metadata = [],
        ?int $userId = null
    ): self {
        return self::create([
            'order_id'    => $orderId,
            'user_id'     => $userId ?? auth()->id(),
            'action'      => $action,
            'description' => $description,
            'metadata'    => $metadata,
            'created_at'  => now(),
        ]);
    }
}
