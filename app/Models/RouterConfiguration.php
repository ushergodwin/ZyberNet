<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RouterConfiguration extends Model
{
    protected $fillable = [
        'name',
        'host',
        'port',
        'username',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    public function getConnectionName()
    {
        return config('database.default');
    }

    public function getTable()
    {
        return 'router_configurations';
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
