<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherPackage extends Model
{
    //
    protected $fillable = [
        'name',
        'price',
        'profile_name',
        'rate_limit',
        'session_timeout',
        'limit_bytes_total',
        'shared_users',
        'description',
        'is_active'
    ];

    protected $appends = [
        'formatted_price',
        'formatted_limit_bytes_total',
    ];

    public function getFormattedPriceAttribute()
    {
        return number_format($this->price, 2) . ' UGX';
    }
    public function getFormattedLimitBytesTotalAttribute()
    {
        if ($this->limit_bytes_total === null) {
            return 'Unlimited';
        }

        $bytes = $this->limit_bytes_total;
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' Bytes';
        }
    }
}