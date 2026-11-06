<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceProductFeature extends Model
{
    protected $table = 'service_product_features';
    
    protected $fillable = ['name', 'service_product_id'];


    public function serviceProduct()
    {
        return $this->belongsTo(ServiceProduct::class);
    }


}
