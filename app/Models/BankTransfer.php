<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankTransfer extends Model
{
    protected $fillable = [
        'account_holder_name',
        'account_number',
        'bank_name',
        'branch_name',
        'note',
        'status',
    ];

    protected $casts = [
        'status' => 'integer',
    ];
}
