<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryType extends Model
{
    protected $fillable = ['name', 'fee', 'days'];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
