<?php

use App\Http\Controllers\Api\Admin\AddOnController;
use App\Http\Controllers\Api\Admin\AccountSettingsController;
use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\OrderRequestController;
use App\Http\Controllers\Api\Admin\VehicleController;
use App\Http\Controllers\Api\Admin\VehicleDiscountController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('admin.token')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/settings/account', [AccountSettingsController::class, 'show']);
        Route::put('/settings/account/profile', [AccountSettingsController::class, 'updateProfile']);
        Route::put('/settings/account/password', [AccountSettingsController::class, 'updatePassword']);

        Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
        Route::get('/dashboard/analytics', [DashboardController::class, 'analytics']);
        Route::get('/dashboard/analytics/{date}/sessions', [DashboardController::class, 'analyticsSessions'])
            ->where('date', '\d{4}-\d{2}-\d{2}');
        Route::get('/dashboard/analytics/sessions/{visitorSession}/page-views', [DashboardController::class, 'analyticsSessionPageViews']);

        Route::get('/vehicles', [VehicleController::class, 'index']);
        Route::post('/vehicles', [VehicleController::class, 'store']);
        Route::put('/vehicles/{vehicle}', [VehicleController::class, 'update']);
        Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy']);

        Route::get('/add-ons', [AddOnController::class, 'index']);
        Route::post('/add-ons', [AddOnController::class, 'store']);
        Route::put('/add-ons/{addOn}', [AddOnController::class, 'update']);
        Route::delete('/add-ons/{addOn}', [AddOnController::class, 'destroy']);

        Route::get('/vehicle-discounts', [VehicleDiscountController::class, 'index']);
        Route::post('/vehicle-discounts', [VehicleDiscountController::class, 'store']);
        Route::put('/vehicle-discounts/{vehicleDiscount}', [VehicleDiscountController::class, 'update']);
        Route::delete('/vehicle-discounts/{vehicleDiscount}', [VehicleDiscountController::class, 'destroy']);

        Route::get('/order-requests', [OrderRequestController::class, 'index']);
        Route::get('/order-requests/{orderRequest}', [OrderRequestController::class, 'show']);
        Route::patch('/order-requests/{orderRequest}/status', [OrderRequestController::class, 'updateStatus']);
    });
});
