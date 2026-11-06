<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZoneWiseDeliveryType extends Model
{
    protected $fillable = [
        'zip_code_group_id',
        'delivery_type_id',
        'fee',
    ];

    public function zipCodeGroup()
    {
        return $this->belongsTo(ZipCodeGroup::class, 'zip_code_group_id');
    }

    public function deliveryType()
    {
        return $this->belongsTo(DeliveryType::class, 'delivery_type_id');
    }
}
