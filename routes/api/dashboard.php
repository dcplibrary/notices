<?php

// Dashboard endpoints
Route::get('/dashboard', [\Dcplibrary\Notices\Http\Controllers\DashboardController::class, 'dashboardApi'])
    ->name('dashboard');
Route::get('/dashboard/stats', [\Dcplibrary\Notices\Http\Controllers\DashboardController::class, 'dashboardStatsApi'])
    ->name('dashboard.stats');
Route::get('/dashboard/import-status', [\Dcplibrary\Notices\Http\Controllers\DashboardController::class, 'importStatusApi'])
    ->name('dashboard.import-status');
