<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;

    public const FREQUENCIES = ['every_week', 'every_2_weeks', 'every_month', 'custom'];

    public const MAX_CUSTOM_DAYS = 365;

    protected $fillable = [
        'user_id',
        'order_id',
        'preferred_date',
        'repeat',
        'status',
        'frequency',
        'custom_days',
        'preferred_time',
        'next_delivery_date',
        'cancel_reason',
        'cancelled_at',
        'shipping_address_id',
        'product_id',
        'load_size',
        'unit_price',
    ];

    // date:Y-m-d keeps the JSON format the shipped app already reads from
    // POST /schedules/store and /schedules/update/{id}.
    protected $casts = [
        'preferred_date' => 'date:Y-m-d',
        'next_delivery_date' => 'date:Y-m-d',
        'cancelled_at' => 'datetime',
        'custom_days' => 'integer',
        'load_size' => 'decimal:2',
        'unit_price' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    public function user() {
        return $this->belongsTo(User::class);
    }

    public function category() {
        return $this->belongsTo(Category::class);
    }

    public function product() {
        return $this->belongsTo(Product::class);
    }

    public function shippingAddress()
    {
        return $this->belongsTo(ShippingAddress::class);
    }

    /**
     * Subscriptions set up through the repeat delivery flow. Excludes the
     * one-off date rows the old scheduling screen created.
     */
    public function scopeRepeatDeliveries(Builder $query): Builder
    {
        return $query->where('repeat', true)->whereNotNull('next_delivery_date');
    }

    public function advanceFrom(Carbon $date): Carbon
    {
        return match ($this->frequency) {
            'every_week' => $date->copy()->addDays(7),
            'every_month' => $date->copy()->addMonthNoOverflow(),
            'custom' => $date->copy()->addDays(max(1, (int) $this->custom_days)),
            default => $date->copy()->addDays(14),
        };
    }

    /**
     * Moves one interval past $date, then keeps going until the result is not
     * in the past, so a stale date can't be "skipped" to another stale date.
     */
    public function nextOccurrenceAfter(Carbon $date): Carbon
    {
        $next = $this->advanceFrom($date);

        while ($next->lt(today())) {
            $next = $this->advanceFrom($next);
        }

        return $next;
    }

    public function getFrequencyLabelAttribute(): string
    {
        return match ($this->frequency) {
            'every_week' => 'Every week',
            'every_month' => 'Every month',
            'custom' => self::customLabel((int) $this->custom_days),
            default => 'Every 2 weeks',
        };
    }

    public function getNextDeliveryDateFormattedAttribute(): ?string
    {
        return $this->next_delivery_date?->format('D j M Y');
    }

    /**
     * Tonnes per unit from a firewood product name such as
     * "2/4 Truck - (8 scoops / 2T)" or "Half Cage - (2 Scoops, HT)".
     */
    public static function tonnesFromProductName(?string $name): ?float
    {
        if (preg_match('/([\d.]+)\s*T\s*\)/i', (string) $name, $matches)) {
            return (float) $matches[1];
        }

        return preg_match('/\bHT\s*\)/i', (string) $name) ? 0.5 : null;
    }

    private static function customLabel(int $days): string
    {
        if ($days % 7 === 0 && $days > 0) {
            $weeks = $days / 7;

            return $weeks === 1 ? 'Every week' : "Every {$weeks} weeks";
        }

        return $days === 1 ? 'Every day' : "Every {$days} days";
    }
}
