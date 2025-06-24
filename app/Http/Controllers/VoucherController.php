<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VoucherController extends Controller
{
    //

    public function index()
    {
        return inertia('Vouchers/Packages', [
            'user' => Auth::user(),
        ]);
    }

    // vouchers 
    public function vouchers()
    {
        return inertia('Vouchers/List', [
            'user' => Auth::user(),
        ]);
    }
}
