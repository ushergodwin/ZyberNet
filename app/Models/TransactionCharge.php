<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionCharge extends Model
{
    //
    protected $fillable = [
        'min_amount',
        'max_amount',
        'charge',
        'network',
    ];

    protected $casts = [
        'min_amount' => 'integer',
        'max_amount' => 'integer',
        'charge' => 'decimal:2',
        'network' => 'string',
    ];
}
