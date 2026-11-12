<?php

// Deduplicated holds (unified notifications - how notices are actually sent)
Route::get('/holds', \Dcplibrary\Notices\Http\Controllers\HoldsDedupedController::class)->name('holds.index');

// Raw Polaris hold export logs (one row per exported notice)
Route::get('/holds/logs', \Dcplibrary\Notices\Http\Controllers\HoldsLogsController::class)->name('holds.logs');

// Raw Polaris renewal and overdue export logs
Route::get('/renew/logs', \Dcplibrary\Notices\Http\Controllers\RenewLogsController::class)->name('renew.logs');
Route::get('/overdue/logs', \Dcplibrary\Notices\Http\Controllers\OverdueLogsController::class)->name('overdue.logs');
