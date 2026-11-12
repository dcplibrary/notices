<?php

// Failed contacts endpoints
Route::get('/failed-contacts', [\Dcplibrary\Notices\Http\Controllers\FailedContactsController::class, 'failedContactsApi'])
    ->name('failed-contacts.index');
Route::get('/failed-contacts/counts', [\Dcplibrary\Notices\Http\Controllers\FailedContactsController::class, 'failedContactCountsApi'])
    ->name('failed-contacts.counts');
Route::get('/failed-contacts/{id}', [\Dcplibrary\Notices\Http\Controllers\FailedContactsController::class, 'showFailedContact'])
    ->whereNumber('id')
    ->name('failed-contacts.show');
Route::post('/failed-contacts/{id}/resolve', [\Dcplibrary\Notices\Http\Controllers\FailedContactsController::class, 'resolveFailedContactApi'])
    ->middleware(['ability:issues:write'])
    ->name('failed-contacts.resolve');
