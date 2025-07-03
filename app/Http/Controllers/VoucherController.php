<?php

namespace App\Http\Controllers;

use App\Models\VoucherPackage;
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

    public function purchase($id = null)
    {
        $package = VoucherPackage::find($id);
        $packages = VoucherPackage::all();
        return inertia('Vouchers/Purchase', [
            'user' => Auth::user(),
            'package_id' => $package ? $package->id : null,
            'packages' => $packages,
            'csrfToken' => csrf_token(),
        ]);
    }
}
