<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class EntryPackage extends Model
{
    use HasFactory;
    protected $fillable = ['entry_quantity', 'price', 'status', 'name', 'description'];

    protected $casts = [
        'price' => 'decimal:2',
        'status' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

}
