<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZipCode extends Model
{
    protected $fillable = [
        'zip_code',
        'status',
        'group_id',
    ];

    /**
     * Get the group this zip code belongs to.
     */
    public function group()
    {
        return $this->belongsTo(ZipCodeGroup::class, 'group_id');
    }

    /**
     * Scope: only zip codes that are fully active (own status + group status).
     */
    public function scopeIsDeliverable($query)
    {
        return $query->where('status', 1)
            ->where(function ($q) {
                $q->whereNull('group_id')
                  ->orWhereHas('group', function ($q2) {
                      $q2->where('status', 1);
                  });
            });
    }
}