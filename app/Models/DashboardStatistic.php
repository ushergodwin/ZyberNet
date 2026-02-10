<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DashboardStatistic extends Model
{
    protected $fillable = [
        'router_id',
        'period',
        'statistics',
        'computed_at',
    ];

    protected $casts = [
        'statistics' => 'array',
        'computed_at' => 'datetime',
    ];

    public function router()
    {
        return $this->belongsTo(RouterConfiguration::class);
    }
}
