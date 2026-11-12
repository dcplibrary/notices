<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Notices Package API Routes
|--------------------------------------------------------------------------
|
| API endpoints for the notices package. These routes return JSON responses
| suitable for use with external dashboards or API clients.
|
| All routes require authentication via the 'api' middleware group.
| The host application should configure API authentication (e.g., Sanctum).
|
*/

Route::prefix('api/v1/notices')
    ->middleware(['api', 'auth:sanctum'])
    ->name('api.notices.')
    ->group(function () {
        require __DIR__ . '/api/dashboard.php';
        require __DIR__ . '/api/notifications.php';
        require __DIR__ . '/api/issues.php';
        require __DIR__ . '/api/failed-contacts.php';
        require __DIR__ . '/api/search.php';
        require __DIR__ . '/api/admin-settings.php';
    });
