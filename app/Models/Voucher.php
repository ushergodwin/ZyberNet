<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class Voucher extends Model
{
    //
    use SoftDeletes;

    protected $fillable = [
        'code',
        'transaction_id',
        'package_id',
        'expires_at',
        'is_used'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_used' => 'boolean',
    ];

    protected $appends = [
        'is_active',
        'formatted_expiry_date',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function package()
    {
        return $this->belongsTo(VoucherPackage::class);
    }
    public function getIsActiveAttribute()
    {
        // return true if the voucher expiry date is in the future
        return Carbon::now()->lessThanOrEqualTo($this->expires_at);
    }

    protected static function booted()
    {
        static::created(function ($voucher) {
            try {
                $router = \App\Models\RouterConfiguration::first();
                if (config('app.env') != 'local') {
                    $mikrotik = new \App\Services\MikroTikService($router);

                    $mikrotik->createHotspotUser(
                        $voucher->code,
                        $voucher->code,
                        $voucher->package->session_timeout,
                        $voucher->package->profile_name
                    );

                    Log::info('Voucher created: ' . $voucher->code, [
                        'voucher' => $voucher,
                    ]);
                }
            } catch (\Throwable $th) {
                Log::error('Failed to create MikroTik user: ' . $th->getMessage());
            }
        });
    }

    public function routerLogs()
    {
        return $this->hasMany(\App\Models\RouterLog::class);
    }

    public function getFormattedExpiryDateAttribute()
    {
        // eg "Expires on 2023-10-31 at 23:59"
        if (!$this->expires_at) {
            return null;
        }

        return $this->expires_at->format('Y-m-d') . ' at ' . $this->expires_at->format('H:i');
    }
}
