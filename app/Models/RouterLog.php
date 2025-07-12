<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RouterLog extends Model
{
    //

    protected $fillable = [
        'voucher',
        'action',
        'success',
        'message',
        'is_manual',
        'router_name',
        'router_id',
    ];

    public function voucher()
    {
        $voucher = Voucher::where('code', $this->voucher)->first();
        if ($voucher) {
            return $voucher;
        }
        return null;
    }

    public function router()
    {
        return $this->belongsTo(RouterConfiguration::class, 'router_id');
    }
}
