<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Giveaway extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'prize_name', 
        'description', 
        'prize_type', 
        'prize_value',
        'image', 
        'draw_date', 
        'draw_time',
        'scheduled_at',
        'start_date',
        'start_time',  // ✅ ONLY change: added this
        'end_date',
        'status', 
        'winner_id', 
        'winner_announced_at',
        'winner_drawn_at',
        'position',
        'allow_temp_entries',
        'max_entries_per_user',
        'auto_draw',
        'required_entries',
        'animation_style',
        'template_id',
        'source',
    ];

    // ✅ KEPT ORIGINAL - don't remove date casts!
    protected $casts = [
        'draw_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'scheduled_at' => 'datetime',
        'winner_announced_at' => 'datetime',
        'winner_drawn_at' => 'datetime',
        'prize_value' => 'decimal:2',
        'allow_temp_entries' => 'boolean',
        'auto_draw' => 'boolean',
    ];

    /**
     * Status constants
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_ACTIVE = '1';
    const STATUS_DRAWING = 'drawing';
    const STATUS_COMPLETED = '0';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Relationships
     */
    public function entries()
    {
        return $this->hasMany(Entry::class);
    }

    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function template()
    {
        return $this->belongsTo(GiveawayTemplate::class, 'template_id');
    }

    public function prize()
    {
        return $this->hasOne(Prize::class);
    }

    /**
     * FIXED: Changed to plural table name 'giveaway_entry_packages'
     * and added is_active and position to pivot
     */
    public function entryPackages()
    {
        return $this->belongsToMany(EntryPackage::class, 'giveaway_entry_packages')
            ->withPivot(['custom_price', 'is_active', 'position'])
            ->withTimestamps();
    }

    public function activeEntryPackages()
    {
        return $this->entryPackages()->where('status', 1);
    }

    public function drawLogs()
    {
        return $this->hasMany(GiveawayDrawLog::class);
    }

    public function lastDrawLog()
    {
        return $this->hasOne(GiveawayDrawLog::class)->latestOfMany();
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeReadyToDraw($query)
    {
        $timezone = 'Australia/Perth';
        $nowInLocal = now()->timezone($timezone);
        $todayLocal = $nowInLocal->toDateString();
        $currentTimeLocal = $nowInLocal->toTimeString();

        return $query->where('status', self::STATUS_ACTIVE)
            ->where('auto_draw', true)
            ->whereNull('winner_id')
            ->where(function($q) use ($nowInLocal, $todayLocal, $currentTimeLocal) {
                $q->where('scheduled_at', '<=', $nowInLocal) // Laravel handles datetime conversion
                  ->orWhere(function($q2) use ($todayLocal, $currentTimeLocal) {
                      $q2->whereRaw("DATE(draw_date) < ?", [$todayLocal])
                         ->orWhere(function($q3) use ($todayLocal, $currentTimeLocal) {
                             $q3->whereRaw("DATE(draw_date) = ?", [$todayLocal])
                                ->where('draw_time', '<=', $currentTimeLocal);
                         });
                  });
            });
    }

    public function scopeUpcoming($query)
    {
        return $query->whereIn('status', [self::STATUS_SCHEDULED, self::STATUS_ACTIVE])
            ->whereNull('winner_id')
            ->where('draw_date', '>=', now()->toDateString());
    }

    /**
     * Methods
     */
    public function totalEntries()
    {
        return Entry::where('drawn', false)
            ->where(function ($q) {
                $q->where('giveaway_id', $this->id)
                  ->orWhereNull('giveaway_id');
            })
            ->sum('entry_count');
    }

    public function totalParticipants()
    {
        return Entry::where('drawn', false)
            ->where(function ($q) {
                $q->where('giveaway_id', $this->id)
                  ->orWhereNull('giveaway_id');
            })
            ->distinct('user_id')
            ->count('user_id');
    }

    public function userEntries($userId)
    {
        return $this->entries()
            ->where('user_id', $userId)
            ->where('drawn', false)
            ->sum('entry_count');
    }

    public function isActive()
    {
        return $this->status === self::STATUS_ACTIVE
            && now()->between($this->start_date, $this->end_date);
    }

    public function canDraw()
    {
        return $this->status === self::STATUS_ACTIVE
            && is_null($this->winner_id)
            && $this->totalEntries() > 0;
    }

    public function isReadyForAutoDraw()
    {
        if (!$this->auto_draw || !$this->canDraw()) {
            return false;
        }

        if ($this->scheduled_at) {
            return $this->scheduled_at <= now();
        }

        if ($this->draw_date && $this->draw_time) {
            // Use the accessor which already handles the timezone conversion to UTC
            $scheduledDateUtc = $this->draw_date_utc;
            return $scheduledDateUtc && $scheduledDateUtc <= now();
        }

        return false;
    }

    public function getEntryPackagePrice($packageId)
    {
        // Use wherePivot to accurately filter the pivot table
        $pivot = $this->entryPackages()->wherePivot('entry_package_id', $packageId)->first();
        
        if ($pivot && $pivot->pivot && $pivot->pivot->custom_price !== null) {
            return $pivot->pivot->custom_price;
        }
        
        $package = EntryPackage::find($packageId);
        return $package ? $package->price : 0;
    }

    /**
     * Check if user can enter this giveaway
     */
    public function canUserEnter($userId)
    {
        if ($this->status !== 'active' && $this->status !== '1') {
            return false;
        }
        
        $now = now();
        if ($this->start_date && $now < $this->start_date) {
            return false;
        }
        if ($this->end_date && $now > $this->end_date) {
            return false;
        }
        
        if ($this->max_entries_per_user) {
            $userEntries = Entry::where('user_id', $userId)
                ->where('giveaway_id', $this->id)
                ->sum('entry_count');
            
            if ($userEntries >= $this->max_entries_per_user) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get remaining entries user can purchase
     */
    public function getRemainingEntriesForUser($userId)
    {
        if (!$this->max_entries_per_user) {
            return null;
        }
        
        $userEntries = Entry::where('user_id', $userId)
            ->where('giveaway_id', $this->id)
            ->sum('entry_count');
        
        return max(0, $this->max_entries_per_user - $userEntries);
    }

    public function getImageAttribute($value)
    {
        if (!$value) {
            return image_asset(null);
        }

        // If already a full URL, return as-is
        if (str_starts_with($value, 'http')) {
            return $value;
        }

        // New giveaway_images path (from new system)
        if (str_starts_with($value, 'giveaway_images/')) {
            return asset($value);
        }

        // Legacy path
        $legacyPath = 'uploads/giveaway_image/' . $value;
        if (file_exists(public_path($legacyPath))) {
            return asset($legacyPath);
        }

        return image_asset($value);
    }

    /**
     * Get status badge color
     */
    public function getStatusBadgeClass()
    {
        return match($this->status) {
            self::STATUS_DRAFT => 'secondary',
            self::STATUS_SCHEDULED => 'info',
            self::STATUS_ACTIVE => 'success',
            self::STATUS_DRAWING => 'warning',
            self::STATUS_COMPLETED => 'primary',
            self::STATUS_CANCELLED => 'danger',
            default => 'secondary'
        };
    }

    /**
     * Get formatted status
     */
    public function getFormattedStatus()
    {
        return ucfirst($this->status);
    }

    /**
     * UTC Date Accessors
     */
    public function getStartDateUtcAttribute()
    {
        if (!$this->start_date) return null;
        $time = $this->start_time ?? '00:00:00';
        return \Carbon\Carbon::parse($this->start_date->format('Y-m-d') . ' ' . $time, 'Australia/Perth')
            ->setTimezone('UTC');
    }

    public function getEndDateUtcAttribute()
    {
        if (!$this->end_date) return null;
        $time = $this->draw_time ?? '23:59:59';
        return \Carbon\Carbon::parse($this->end_date->format('Y-m-d') . ' ' . $time, 'Australia/Perth')
            ->setTimezone('UTC');
    }

    public function getDrawDateUtcAttribute()
    {
        if (!$this->draw_date) return null;
        $time = $this->draw_time ?? '00:00:00';
        return \Carbon\Carbon::parse($this->draw_date->format('Y-m-d') . ' ' . $time, 'Australia/Perth')
            ->setTimezone('UTC');
    }

    public function getNumericPriceAttribute()
    {
        // Giveaways usually have a prize_value, but if CartController expects 'price'
        // we'll provide a sanitized version of it if it exists, otherwise 0.
        $priceValue = $this->price ?? 0;
        if (is_numeric($priceValue)) {
            return (float) $priceValue;
        }
        $priceValue = preg_replace('/[^0-9.]/', '', (string) $priceValue);
        return $priceValue === '' ? 0.0 : (float) $priceValue;
    }

    /**
     * Fill the weekly slot once nothing is live: a manually scheduled giveaway first,
     * otherwise the next pick from the giveaway pool. See GiveawayPoolService.
     */
    public static function activateNextScheduled()
    {
        try {
            return app(\App\Services\Giveaways\GiveawayPoolService::class)->ensureActive();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('activateNextScheduled failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Broadcast push notification to all users about a new active giveaway
     */
    public static function broadcastNewGiveawayNotification(self $giveaway)
    {
        try {
            $users = User::whereNotNull('fcm_token')->where('is_admin', 0)->get();
            
            foreach ($users as $user) {
                $user->notify(new \App\Notifications\CategoryNotification('updates', [
                    'subject' => 'New Giveaway!',
                    'body'    => "🎁 {$giveaway->prize_name} — Enter now for a chance to win!",
                    'title'   => 'New Giveaway Available!',
                    'screen'  => 'giveaway_details',
                    'data'    => [
                        'giveaway_id' => (string) $giveaway->id,
                        'type' => 'new_giveaway',
                    ]
                ]));
            }
            
            \Illuminate\Support\Facades\Log::info("Auto-activation push notifications sent", [
                'giveaway_id' => $giveaway->id,
                'users_notified' => $users->count(),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to send auto-activation push notifications", [
                'giveaway_id' => $giveaway->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}