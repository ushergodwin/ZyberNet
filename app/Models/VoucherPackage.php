<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherPackage extends Model
{
    protected $fillable = [
        'name',
        'price',
        'profile_name',
        'rate_limit',
        'session_timeout',
        'limit_bytes_total',
        'shared_users',
        'description',
        'is_active',
        'router_id',
    ];

    protected $appends = [
        'formatted_price',
        'formatted_limit_bytes_total',
        'js_rate_limit',
        'rate_limit_unit',
        'js_session_timeout',
        'session_timeout_unit',
        'js_limit_bytes_total',
        'limit_bytes_unit',
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

    public function getJsRateLimitAttribute()
    {
        if (!$this->rate_limit) return null;
        preg_match('/(\d+)([kKmMgG])?\/?/', $this->rate_limit, $matches);
        return isset($matches[1]) ? (int) $matches[1] : null;
    }

    public function getRateLimitUnitAttribute()
    {
        if (!$this->rate_limit) return null;
        preg_match('/(\d+)([kKmMgG])?\/?/', $this->rate_limit, $matches);
        if (isset($matches[2])) {
            $unit = strtoupper($matches[2]);
            return $unit === 'K' ? 'Kbps' : ($unit === 'G' ? 'Gbps' : 'Mbps');
        }
        return 'Mbps'; // default
    }

    public function getJsSessionTimeoutAttribute()
    {
        if (!$this->session_timeout) return null;
        return (int) preg_replace('/[^0-9]/', '', $this->session_timeout);
    }

    public function getSessionTimeoutUnitAttribute()
    {
        if (!$this->session_timeout) return null;
        $unit = strtolower(substr($this->session_timeout, -1));
        return $unit === 'd' ? 'days' : 'hours';
    }

    public function getJsLimitBytesTotalAttribute()
    {
        if ($this->limit_bytes_total === null) return null;

        if ($this->limit_bytes_total >= 1073741824) {
            return round($this->limit_bytes_total / 1073741824); // in GB
        } else {
            return round($this->limit_bytes_total / 1048576); // in MB
        }
    }

    public function getLimitBytesUnitAttribute()
    {
        if ($this->limit_bytes_total === null) return null;

        return $this->limit_bytes_total >= 1073741824 ? 'GB' : 'MB';
    }

    public function router()
    {
        return $this->belongsTo(RouterConfiguration::class, 'router_id');
    }
}
