<?php

// Notifications endpoints
Route::get('/notifications', [\Dcplibrary\Notices\Http\Controllers\NotificationsController::class, 'notificationsApi'])
    ->name('notifications.index');
Route::get('/notifications/patron/{barcode}', [\Dcplibrary\Notices\Http\Controllers\NotificationsController::class, 'patronApi'])
    ->name('notifications.patron');
Route::get('/notifications/{id}', [\Dcplibrary\Notices\Http\Controllers\NotificationsController::class, 'show'])
    ->whereNumber('id')
    ->name('notifications.show');
