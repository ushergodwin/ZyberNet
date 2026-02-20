<?php

namespace App\Http\Controllers;

use App\Models\RouterConfiguration;
use App\Models\SupportContact;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Models\VoucherPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;

class HotspotController extends Controller
{
    public function index()
    {

        $plans = VoucherPackage::where('router_id', 1)->get();
        $supportContacts = SupportContact::whereNull('router_id')->get();
        return view('hotspot.index', [
            'plans' => $plans,
            'supportContacts' => $supportContacts,
        ]);
    }

    public function authenticate(Request $request)
    {
        $request->validate([
            'voucher_code' => 'required',
        ]);

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

    // activate voucher 
    public function success(Request $request)
    {
        $code = $request->query('username');

        if ($code) {
            $voucher = Voucher::with('package')
                ->where('code', $code)
                ->whereNull('activated_at')
                ->first();

            if ($voucher) {
                $timeoutValue = (int) preg_replace('/[^0-9]/', '', $voucher->package->session_timeout);
                $timeoutUnit  = strtolower(substr($voucher->package->session_timeout, -1));

                // Calculate expires_at from activation time
                $expiresAt = now()->add(
                    $timeoutUnit === 'd' ? $timeoutValue . ' days' : $timeoutValue . ' hours'
                );

                $voucher->update([
                    'activated_at' => now(),
                    'expires_at'   => $expiresAt,
                ]);

                // Auto-create a cash transaction for shop-sold (admin-printed) vouchers
                if ($voucher->gateway === 'shop' && is_null($voucher->transaction_id)) {
                    try {
                        $phone = SupportContact::where('router_id', $voucher->router_id)->value('phone_number')
                              ?? SupportContact::whereNull('router_id')->value('phone_number')
                              ?? 'N/A';

                        $transaction = Transaction::create([
                            'phone_number' => $phone,
                            'amount'       => $voucher->package->price,
                            'currency'     => 'UGX',
                            'status'       => 'successful',
                            'channel'      => 'cash',
                            'gateway'      => 'shop',
                            'package_id'   => $voucher->package_id,
                            'router_id'    => $voucher->router_id,
                            'charge'       => 0,
                            'total_amount' => $voucher->package->price,
                        ]);

                        $voucher->update(['transaction_id' => $transaction->id]);
                    } catch (\Throwable $e) {
                        Log::error("Failed to create cash transaction for shop voucher {$voucher->code}: " . $e->getMessage());
                    }
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }


    // buyVoucher
    public function buyVoucher($id = null)
    {
        $package = VoucherPackage::find($id);
        // Render the buy voucher page with the package details
        $link_login = session('link_login', null);
        // get csrfToken
        $csrfToken = csrf_token();
        $supportContacts = [];
        if ($package) {
            $supportContacts = SupportContact::where('router_id', $package->router_id)->get();
        } else {
            $supportContacts = SupportContact::whereNull('router_id')->get();
        }
        $packages = $package
            ? VoucherPackage::where('router_id', $package->router_id)->where('is_active', true)->get()
            : VoucherPackage::where('is_active', true)->get();

        return view('hotspot.buy', [
            'package_id' => $package ? $package->id : null,
            'packages' => $packages,
            'wifi_name' => config('app.name', 'Hotspot WiFi'),
            'link_login' => $link_login,
            'supportContacts' => $supportContacts,
        ]);
    }

    public function showWiFiLogin(Request $request)
    {
        $link_login = $request->input('link-login', null);
        $link_orig = $request->input('link-orig', null);
        $mac = $request->input('mac', null);
        $ip = $request->input('ip', null);
        $router_id = $request->input('router_id', null);

        // check router exists
        RouterConfiguration::findOrFail($router_id);
        // store session data
        Session::put([
            'link_login' => $link_login,
            'link_orig'  => $link_orig,
            'mac'        => $mac,
            'ip'         => $ip,
            'router_id'  => $router_id,
        ]);

        $plans = VoucherPackage::where('router_id', $router_id)
            ->where('is_active', true)
            ->get();

        $supportContacts = [];
        if ($router_id) {
            $supportContacts = SupportContact::where('router_id', $router_id)->get();
        } else {
            $supportContacts = SupportContact::whereNull('router_id')->get();
        }
        return view('hotspot.login', [
            'link_login' => $link_login,
            'link_orig'  => $link_orig,
            'mac'        => $mac,
            'ip'         => $ip,
            'plans'      => $plans,
            'router_id'  => $router_id,
            'error'      => $request->query('error', null),
            'supportContacts' => $supportContacts
        ]);
    }
}
