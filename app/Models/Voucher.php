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
        'is_used',
        'router_id',
        'activated_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_used' => 'boolean',
    ];

    protected $appends = [
        'is_active',
        'formatted_expiry_date',
        'expires_in',
        'activated_at_time'
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
        // Unactivated vouchers haven't started their countdown yet — not expired
        if (is_null($this->activated_at)) {
            return true;
        }

        // Calculate expiry from activated_at + package duration (source of truth)
        // instead of relying on expires_at which may not be recalculated on activation
        if ($this->relationLoaded('package') && $this->package) {
            $timeout = $this->package->session_timeout;
            $timeoutValue = (int) preg_replace('/[^0-9]/', '', $timeout);
            $timeoutUnit = strtolower(substr($timeout, -1));

            $expiresAt = Carbon::parse($this->activated_at)->add(
                $timeoutUnit === 'd' ? $timeoutValue . ' days' : $timeoutValue . ' hours'
            );

            return Carbon::now()->lessThanOrEqualTo($expiresAt);
        }

        return Carbon::now()->lessThanOrEqualTo($this->expires_at);
    }

    // MikroTik hotspot user creation is handled by VoucherService::createVouchersAndPushToRouter()
    // which creates the user on the correct router BEFORE Voucher::create() is called.

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

    public function router()
    {
        return $this->belongsTo(RouterConfiguration::class, 'router_id');
    }

    // get the time remaining before expiry
    public function getExpiresInAttribute()
    {
        $profile_name = $this->package->session_timeout;
        // get the last letter of the profile name
        $lastLetter = substr($profile_name, -1);

        // get the profile name without the last letter
        $profileNameWithoutLastLetter = substr($profile_name, 0, -1);

        // formulate expiry word i.e if $lastLetter is 'd' then 'days', if 'h' then 'hours'
        $expiryWord = $lastLetter === 'd' ? 'days' :  'hours';
        return $profileNameWithoutLastLetter . ' ' . ucfirst($expiryWord);
    }

    public function getActivatedAtTimeAttribute()
    {
        if (is_null($this->activated_at)) {
            return '-';
        }

        $activatedAt = Carbon::parse($this->activated_at);

        if ($activatedAt->isToday()) {
            return $activatedAt->format('h:i A'); // Example: "03:45 PM"
        }

        return $activatedAt->format('Y-m-d h:i A'); // Example: "2025-07-24 03:45 PM"
    }
}
