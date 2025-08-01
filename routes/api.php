<?php

use App\Http\Controllers\Api\ConfigurationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\RouterController;
use App\Http\Controllers\Api\VoucherController;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
// api routes
Route::middleware('auth:sanctum')->group(function () {
    // Define your API routes here
    Route::prefix('configuration')->group(function () {
        // routers 
        Route::get('/routers', [ConfigurationController::class, 'getRouters'])->name('configuration.routers');
        Route::get('/routers/{id}', [ConfigurationController::class, 'getRouter'])->name('configuration.router');
        Route::post('/routers', [ConfigurationController::class, 'createRouter'])->name('configuration.createRouter');
        Route::put('/routers/{id}', [ConfigurationController::class, 'updateRouter'])->name('configuration.updateRouter');
        Route::delete('/routers/{id}', [ConfigurationController::class, 'deleteRouter'])->name('configuration.deleteRouter');
        // test router connection
        Route::post('/routers/{id}/test', [RouterController::class, 'testConnection'])->name('configuration.testRouterConnection');
        // getRouterLogs
        Route::get('/router-logs', [RouterController::class, 'getRouterLogs'])->name('configuration.getRouterLogs');
        // voucher packages
        Route::prefix('/vouchers')->group(function () {
            Route::get('/packages', [ConfigurationController::class, 'getVoucherPackages'])->name('configuration.voucherPackages');
            Route::get('/packages/{id}', [ConfigurationController::class, 'getVoucherPackage'])->name('configuration.voucherPackage');
            Route::post('/packages', [ConfigurationController::class, 'createVoucherPackage'])->name('configuration.createVoucherPackage');
            Route::put('/packages/{id}', [ConfigurationController::class, 'updateVoucherPackage'])->name('configuration.updateVoucherPackage');
            Route::delete('/packages/{id}', [ConfigurationController::class, 'deleteVoucherPackage'])->name('configuration.deleteVoucherPackage');
            //toggle
            Route::post('/packages/{id}/toggle', [ConfigurationController::class, 'toggleVoucherPackage'])->name('configuration.toggleVoucherPackage');

            // vouchers
            Route::get('/', [VoucherController::class, 'getVouchers']);
        });

        // users 
        Route::get('/users', [ConfigurationController::class, 'getUsers'])->name('configuration.users');
        // register user
        Route::post('/users', [AuthController::class, 'register'])->name('configuration.registerUser');
        // update user
        Route::put('/users/{id}', [AuthController::class, 'updateUser'])->name('configuration.updateUser');
        // delete user
        Route::delete('/users/{id}', [AuthController::class, 'deleteUser'])->name('configuration.deleteUser');
        ///restore
        Route::post('/users/{id}/restore', [AuthController::class, 'restoreUser'])->name('configuration.restoreUser');

        //support-contacts
        Route::get('/support-contacts', [ConfigurationController::class, 'getSupportContacts'])->name('configuration.supportContacts');
        // save support-contacts (POST)
        Route::post('/support-contacts', [ConfigurationController::class, 'saveSupportContact'])->name('configuration.saveSupportContacts');

        //delete support contact
        Route::delete('/support-contacts/{id}', [ConfigurationController::class, 'deleteSupportContact'])->name('configuration.deleteSupportContact');
    });

    Route::prefix('/vouchers')->group(function () {
        // vouchers
        Route::get('/', [VoucherController::class, 'getVouchers']);
        //getVoucher
        Route::get('/{id}', [VoucherController::class, 'getVoucher']);
        //get voucher transaction details
        Route::get('/{id}/transaction', [VoucherController::class, 'getVoucherTransaction'])->name('configuration.voucherTransaction');
        // save transaction
        Route::post('/{id}/transaction', [VoucherController::class, 'saveVoucherTransaction'])->name('configuration.saveVoucherTransaction');
        // generate voucher
        Route::post('/generate', [VoucherController::class, 'generateVoucher'])->name('configuration.generateVoucher');
        // destroy voucher
        Route::delete('/{code}', [VoucherController::class, 'destroy'])->name('configuration.destroyVoucher');
    });

    //admin routes
    Route::prefix('transactions')->group(function () {
        Route::get('/', [PaymentController::class, 'getTransactions'])->name('admin.payments');
    });

    // reports 
    Route::prefix('reports')->group(function () {
        //getStatistics from ReportsController
        Route::get('/stats', [ReportsController::class, 'getStatistics'])->name('reports.statistics');
    });
});

// payment routes
Route::prefix('payments')->group(function () {
    Route::post('/voucher', [PaymentController::class, 'purchaseVoucher'])->name('payment.purchaseVoucher');
    // check transaction status
    Route::get('/voucher/status/{id}', [PaymentController::class, 'checkTransactionStatus'])->name('payment.checkVoucherStatus');
});
Route::get('configuration/vouchers/packages', [ConfigurationController::class, 'getVoucherPackages'])->name('configuration.voucherPackages');
// sync vouchers 
Route::get('configuration/vouchers/sync', [VoucherController::class, 'syncVouchersFromLocalHost'])->name('configuration.syncVouchers');