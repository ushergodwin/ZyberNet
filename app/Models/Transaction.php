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
        'router_id'
    ];

    protected $appends = [
        'formatted_amount',
        'voucher'
    ];
    public function package()
    {
        return $this->belongsTo(VoucherPackage::class, 'package_id');
    }

    public function getFormattedAmountAttribute()
    {
        return number_format($this->amount, 2) . ' ' . $this->currency;
    }

    public function getVoucherAttribute()
    {
        $voucher = Voucher::where('transaction_id', $this->id)->first();
        if ($voucher) {
            return $voucher;
        }
        return null;
    }

    public function router()
    {
        return $this->belongsTo(RouterConfiguration::class, 'router_id');
    }

    public function voucher()
    {
        return $this->hasOne(Voucher::class, 'transaction_id');
    }
}
