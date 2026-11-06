<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportImage extends Model
{
    protected $fillable = [
        'report_id',
        'image',
    ];

    public function report()
    {
        return $this->belongsTo(Report::class);
    }

    public function getImageAttribute($value)
    {
        return image_asset($value);
    }
}
