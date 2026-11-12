<?php

// Root redirects - redirect to notices-viewer for all users
Route::get('/', function () {
    return redirect()->route('notices.user-portal.index');
});
Route::get('/dashboard', [\Dcplibrary\Notices\Http\Controllers\DashboardController::class, 'dashboard'])->name('dashboard');
