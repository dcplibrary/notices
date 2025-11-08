<?php

use Dcplibrary\PolarisNotifications\Http\Controllers\DashboardController;
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
