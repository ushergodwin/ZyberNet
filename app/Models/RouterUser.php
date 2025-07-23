<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RouterUser extends Model
{
    //

    protected $fillable = [
        'user_id',
        'router_id',
    ];
}