<?php

/*
|--------------------------------------------------------------------------
| User Portal Routes
|--------------------------------------------------------------------------
|
| Simplified notification status viewer for users with role='user'.
| Also accessible to admins for testing/preview purposes.
|
*/

Route::prefix('notices-viewer')->name('user-portal.')->group(function () {
    // Main notification status viewer
    Route::get('/', [\Dcplibrary\Notices\Http\Controllers\UserPortalController::class, 'index'])
        ->name('index');

    // API endpoint for async search (with loading indicator)
    Route::get('/search', [\Dcplibrary\Notices\Http\Controllers\UserPortalController::class, 'search'])
        ->name('search');

    // Get notification details for modal
    Route::get('/{id}', [\Dcplibrary\Notices\Http\Controllers\UserPortalController::class, 'show'])
        ->whereNumber('id')
        ->name('show');
});
