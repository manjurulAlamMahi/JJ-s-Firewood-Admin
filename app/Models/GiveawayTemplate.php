<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiveawayTemplate extends Model
{
    protected $fillable = [
        'prize_name',
        'description',
        'prize_type',
        'prize_value',
        'image',
        'max_entries_per_user',
        'required_entries',
        'animation_style',
        'is_active',
        'times_used',
        'last_used_at',
    ];

    protected $casts = [
        'prize_value' => 'decimal:2',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function entryPackages()
    {
        return $this->belongsToMany(EntryPackage::class, 'giveaway_template_entry_packages')
            ->withPivot(['custom_price'])
            ->withTimestamps();
    }

    public function giveaways()
    {
        return $this->hasMany(Giveaway::class, 'template_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Public URL for the stored image path (same rules as Giveaway::getImageAttribute).
     */
    public function getImageUrlAttribute(): ?string
    {
        $value = $this->attributes['image'] ?? null;
        if (!$value) {
            return function_exists('image_asset') ? image_asset(null) : null;
        }
        if (str_starts_with($value, 'http')) {
            return $value;
        }
        if (str_starts_with($value, 'giveaway_images/')) {
            return asset($value);
        }
        $legacy = 'uploads/giveaway_image/' . $value;
        if (file_exists(public_path($legacy))) {
            return asset($legacy);
        }
        return function_exists('image_asset') ? image_asset($value) : asset($value);
    }
}
