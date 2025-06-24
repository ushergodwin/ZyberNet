<?php

use App\Http\Controllers\PaymentsController;
use App\Http\Controllers\RouterController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VoucherController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

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
    });
    // show logs
    Route::get('/routers/logs', [RouterController::class, 'logs'])->name('routers.logs');
    // vouchers
    Route::get('/vouchers', [VoucherController::class, 'vouchers'])->name('vouchers.list');
    // payments
    Route::get('/payments', [PaymentsController::class, 'index'])->name('payments.index');
});
