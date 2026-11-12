<?php

// Notification list - searchable/filterable
Route::get('/list', [\Dcplibrary\Notices\Http\Controllers\NotificationsController::class, 'index'])->name('index');

// AJAX search endpoint for admin list
Route::get('/list/search', [\Dcplibrary\Notices\Http\Controllers\NotificationsController::class, 'search'])->name('list.search');

// API endpoint for notification details (used by modal)
Route::get('/api/notifications/{id}', [\Dcplibrary\Notices\Http\Controllers\NotificationsController::class, 'show'])
    ->name('api.notifications.show');

// Patron notification history
Route::get('/patron/{barcode}', [\Dcplibrary\Notices\Http\Controllers\NotificationsController::class, 'patron'])
    ->name('patron')
    ->where('barcode', '\d+');
