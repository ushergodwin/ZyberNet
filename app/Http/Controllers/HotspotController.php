<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use App\Models\VoucherPackage;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HotspotController extends Controller
{
    public function showLogin(Request $request)
    {
        session([
            'link_login' => $request->query('link-login'),
            'link_orig'  => $request->query('link-orig'),
            'mac'        => $request->query('mac'),
            'ip'         => $request->query('ip'),
        ]);

        $plans = VoucherPackage::all();
        return view('hotspot.login', [
            'link_login' => $request->query('link-login'),
            'link_orig'  => $request->query('link-orig'),
            'mac'        => $request->query('mac'),
            'ip'         => $request->query('ip'),
            'plans'      => $plans,
            'error' => $request->query('error', null),
        ]);
    }

    public function authenticate(Request $request)
    {
        $request->validate([
            'voucher_code' => 'required',
        ]);
        // check if the voucher code is valid
        $voucher = Voucher::where('code', $request->voucher_code)->first();
        $error = null;
        if (!$voucher) {
            $error = 'Invalid voucher code.';
        } elseif ($voucher->is_expired) {
            $error = 'This voucher has expired.';
        }

        if ($error) {
            // If the voucher is invalid or expired, redirect back with an error message
            return redirect()->back()->with('error', $error);
        }

        $link_login = session('link_login', $request->query('link-login'));

        if (!$link_login) {
            $link_login = route('hotspot.login');
        }
        return view('hotspot.submit', [
            'voucher'    => $request->voucher_code,
            'link_login' => $link_login,
        ]);
    }

    public function logout(Request $request)
    {
        // Clear the session data
        $request->session()->forget(['link_login', 'link_orig', 'mac', 'ip']);

        // Redirect to the login page or any other page
        return redirect()->route('hotspot.login');
    }

    // success page
    public function login(Request $request)
    {
        // Clear the session data
        $request->session()->forget(['link_login', 'link_orig', 'mac', 'ip']);
        return redirect()->route('hotspot.success');
    }

    // success page
    public function success(Request $request)
    {
        // Clear the session data
        $request->session()->forget(['link_login', 'link_orig', 'mac', 'ip']);
        return view('hotspot.success');
    }

    // buyVoucher
    public function buyVoucher($id)
    {
        $package = VoucherPackage::findOrFail($id);
        // Check if the package is available
        if (!$package) {
            return redirect()->back()->with('error', 'This package is not available.');
        }
        // Render the buy voucher page with the package details

        // get csrfToken
        $csrfToken = csrf_token();
        return Inertia::render('Vouchers/Buy', [
            'package_id' => $package->id,
            'csrfToken' => $csrfToken
        ]);
    }
}