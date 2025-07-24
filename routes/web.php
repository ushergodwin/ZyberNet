<?php
// routes/web.php
use App\Http\Controllers\HotspotController;
use App\Http\Controllers\PaymentsController;
use App\Http\Controllers\RouterController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VoucherController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

Route::get('/', [HotspotController::class, 'index'])->name('hotspot.index');
Route::get('/connect', [HotspotController::class, 'showWiFiLogin'])->name('hotspot.login');
Route::post('/hotspot-login', [HotspotController::class, 'authenticate']);
Route::post('/hotspot-link-login', [HotspotController::class, 'login'])->name('hotspot.authenticate');
Route::get('/hotspot-login-successful', [HotspotController::class, 'success'])->name('hotspot.success');
Route::get('/buy-voucher/{id}', [HotspotController::class, 'buyVoucher'])->name('hotspot.buyVoucher');

// authenticated routes
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        $user = Auth::user();
        if (!$user->api_token) {
            $user->api_token = $user->createToken('ui-token')->plainTextToken;
            $user->save();
        }

        return Inertia::render('Dashboard');
    })->name('dashboard');

    // configuration
    Route::prefix('configuration')->group(function () {
        Route::get('/routers', [RouterController::class, 'index'])->name('routers.index');
        Route::get('/voucher-packages', [VoucherController::class, 'index'])->name('vouchers.index');
        // users
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        // settings
        Route::get('/settings', [SettingsController::class, 'settings'])->name('settings.index');
    });
    // show logs
    Route::get('/routers/logs', [RouterController::class, 'logs'])->name('routers.logs');
    // vouchers
    Route::get('/vouchers', [VoucherController::class, 'vouchers'])->name('vouchers.list');
    // purchase voucher
    Route::get('/vouchers/purchase/{id?}', [VoucherController::class, 'purchase'])->name('vouchers.purchase');
    // payments
    Route::get('/payments', [PaymentsController::class, 'index'])->name('payments.index');
});


Route::middleware(['web'])->group(function () {
    Route::post('/login', function (Request $request) {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            return redirect()->intended('/dashboard');
        }

        throw ValidationException::withMessages([
            'email' => ['The provided credentials do not match our records.'],
        ]);
    })->name('login');

    Route::post('/logout', function (Request $request) {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Redirect correctly depending on request type
        return redirect()->intended('/login');
    })->name('logout');
});