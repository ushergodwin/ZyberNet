<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherPackage extends Model
{
    //
    protected $fillable = [
        'name',
        'duration_minutes', // How long the voucher lasts
        'price',             // Price in UGX
        'speed_limit',       // Optional KBps
        'is_active',
        'profile'
    ];

    protected $appends = [
        'formatted_price',
        'duration_hours',
        'duration_days'
    ];

    public function getFormattedPriceAttribute()
    {
        return number_format($this->price, 2) . ' UGX';
    }

    public function getDurationHoursAttribute()
    {
        return floor($this->duration_minutes / 60);
    }
    public function getDurationDaysAttribute()
    {
        return floor($this->duration_minutes / 1440); // 1440 minutes in a day
    }
}
