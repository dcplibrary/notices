<?php

use Dcplibrary\Notifications\Http\Controllers\DashboardController;
use Dcplibrary\Notifications\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| These routes provide the default dashboard interface.
| They are prefixed with the route defined in config/notifications.php
| (default: notifications)
|
*/

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/notifications', [DashboardController::class, 'notifications'])->name('notifications');
Route::get('/analytics', [DashboardController::class, 'analytics'])->name('analytics');
Route::get('/shoutbomb', [DashboardController::class, 'shoutbomb'])->name('shoutbomb');

// Settings management routes
Route::prefix('settings')->name('settings.')->group(function () {
    Route::get('/', [SettingsController::class, 'index'])->name('index');
    Route::get('/scoped', [SettingsController::class, 'scoped'])->name('scoped');
    Route::post('/', [SettingsController::class, 'store'])->name('store');
    Route::get('/{id}', [SettingsController::class, 'show'])->name('show');
    Route::get('/{id}/edit', [SettingsController::class, 'edit'])->name('edit');
    Route::put('/{id}', [SettingsController::class, 'update'])->name('update');
    Route::delete('/{id}', [SettingsController::class, 'destroy'])->name('destroy');
});
