<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use SoftDeletes;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $fillable = [
        'amount',
        'phone_number',
        'currency',
        'status',
        'payment_id',
        'mfscode',
        'package_id',
        'response_json',
        'channel',
        'router_id',
        'charge',
        'total_amount',
        'gateway',
    ];

    protected $appends = [
        'formatted_amount',
        'formatted_charge',
        'formatted_total_amount',
    ];
    public function package()
    {
        return $this->belongsTo(VoucherPackage::class, 'package_id');
    }

    public function getFormattedAmountAttribute()
    {
        return number_format($this->amount, 2);
    }

    public function router()
    {
        return $this->belongsTo(RouterConfiguration::class, 'router_id');
    }

    public function voucher()
    {
        return $this->hasOne(Voucher::class)->withTrashed();
    }

    public function getFormattedChargeAttribute()
    {
        return number_format($this->charge, 2);
    }

    public function getFormattedTotalAmountAttribute()
    {
        return number_format($this->total_amount, 2);
    }
}
