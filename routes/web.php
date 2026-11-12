<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Package Web Routes
|--------------------------------------------------------------------------
|
| All routes for the dcplibrary/notices package.
| These routes are automatically loaded by the package service provider.
|
*/

Route::middleware(['web', 'auth', 'notices.user-role'])->group(function () {
    require __DIR__ . '/web/global-search.php';

    Route::prefix('notices')
        ->name('notices.')
        ->group(function () {
            // User portal (accessible to all authenticated users)
            require __DIR__ . '/web/user-portal.php';

            // Admin views (full navigation) - users with role='user' are redirected by middleware
            require __DIR__ . '/web/dashboard.php';
            require __DIR__ . '/web/notifications.php';
            require __DIR__ . '/web/issues.php';
            require __DIR__ . '/web/data.php';
            require __DIR__ . '/web/holds.php';
            require __DIR__ . '/web/settings.php';
            require __DIR__ . '/web/api-docs.php';
        });
});
