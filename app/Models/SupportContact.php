<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportContact extends Model
{

    protected $fillable = [
        'type',
        'phone_number',
        'email',
        'router_id',
    ];

    protected $appends = [
        'formatted_phone_number',
    ];

    public function router()
    {
        return $this->belongsTo(RouterConfiguration::class, 'router_id');
    }

    // append +256 on the phone number and remove any leading zero
    public function getFormattedPhoneNumberAttribute()
    {
        $phone = $this->phone_number;
        if (preg_match('/^0/', $phone)) {
            $phone = substr($phone, 1);
        }
        return '+256' . $phone;
    }
}