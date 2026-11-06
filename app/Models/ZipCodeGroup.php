<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZipCodeGroup extends Model
{
    protected $fillable = [
        'name',
        'status',
        'sort_order',
    ];

    /**
     * Get all zip codes belonging to this group.
     */
    public function zipCodes()
    {
        return $this->hasMany(ZipCode::class, 'group_id');
    }

    /**
     * Get all delivery types with fees for this group.
     */
    public function zoneWiseDeliveryTypes()
    {
        return $this->hasMany(ZoneWiseDeliveryType::class, 'zip_code_group_id');
    }

    /**
     * Get only active zip codes in this group.
     */
    public function activeZipCodes()
    {
        return $this->hasMany(ZipCode::class, 'group_id')->where('status', 1);
    }

    /**
     * Get the count of zip codes in this group.
     */
    public function getZipCodeCountAttribute()
    {
        return $this->zipCodes()->count();
    }

    /**
     * Get a preview string of zip codes (first few).
     */
    public function getZipCodePreviewAttribute()
    {
        $codes = $this->zipCodes()->orderBy('zip_code')->limit(8)->pluck('zip_code')->toArray();
        $total = $this->zipCodes()->count();

        if (empty($codes)) {
            return 'No zip codes';
        }

        $preview = implode(', ', $codes);
        if ($total > 8) {
            $preview .= ' and ' . ($total - 8) . ' more';
        }

        return $preview;
    }
}