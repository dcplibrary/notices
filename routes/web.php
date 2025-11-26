<?php

use Dcplibrary\Notices\Http\Controllers\DashboardController;
use Dcplibrary\Notices\Http\Controllers\ExportController;
use Dcplibrary\Notices\Http\Controllers\SettingsController;
use Dcplibrary\Notices\Http\Controllers\SyncController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| These routes provide the default dashboard interface.
| They are prefixed with the route defined in config/notices.php
| (default: notices)
|
*/

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/list', [DashboardController::class, 'notifications'])->name('list');
Route::get('/list/{id}', [DashboardController::class, 'notificationDetail'])->name('notification.detail');
Route::get('/analytics', [DashboardController::class, 'analytics'])->name('analytics');
Route::get('/shoutbomb', [DashboardController::class, 'shoutbomb'])->name('shoutbomb');
Route::get('/troubleshooting', [DashboardController::class, 'troubleshooting'])->name('troubleshooting');
Route::get('/troubleshooting/export', [DashboardController::class, 'exportFailures'])->name('troubleshooting.export');

// Verification routes
Route::prefix('verification')->name('verification.')->group(function () {
    Route::get('/', [DashboardController::class, 'verification'])->name('index');
    Route::get('/export', [DashboardController::class, 'exportVerification'])->name('export');
    Route::get('/patron/{barcode}', [DashboardController::class, 'patronHistory'])->name('patron');
    Route::get('/patron/{barcode}/export', [DashboardController::class, 'exportPatronHistory'])->name('patron.export');
    Route::get('/{id}', [DashboardController::class, 'timeline'])->name('timeline');
});

// Settings management routes - Admin only (Computer Services group)
Route::prefix('settings')->name('settings.')->group(function () {
    Route::get('/', [SettingsController::class, 'index'])->name('index');
    Route::get('/reference-data', [SettingsController::class, 'referenceData'])->name('reference-data');
    Route::get('/sync', [SettingsController::class, 'sync'])->name('sync');
    Route::get('/export', [SettingsController::class, 'export'])->name('export');
    Route::get('/scoped', [SettingsController::class, 'scoped'])->name('scoped');
    Route::post('/', [SettingsController::class, 'store'])->name('store');
    Route::get('/{id}', [SettingsController::class, 'show'])->name('show');
    Route::get('/{id}/edit', [SettingsController::class, 'edit'])->name('edit');
    Route::put('/{id}', [SettingsController::class, 'update'])->name('update');
    Route::delete('/{id}', [SettingsController::class, 'destroy'])->name('destroy');

    // Reference data management
    Route::put('/notification-type/{id}', [SettingsController::class, 'updateNotificationType'])->name('update-notification-type');
    Route::put('/delivery-method/{id}', [SettingsController::class, 'updateDeliveryMethod'])->name('update-delivery-method');
    Route::put('/notification-status/{id}', [SettingsController::class, 'updateNotificationStatus'])->name('update-notification-status');

    // Integrations
    Route::post('/integrations/shoutbomb-reports/toggle', [SettingsController::class, 'toggleShoutbombReports'])
        ->name('integrations.shoutbomb-reports.toggle');

    // Data tools
    Route::post('/tools/normalize-phones', [SettingsController::class, 'normalizePhones'])->name('tools.normalize-phones');
});

// Sync/Import routes - Admin only
Route::prefix('sync')->name('sync.')->group(function () {
    Route::post('/all', [SyncController::class, 'syncAll'])->name('all');
    Route::post('/polaris', [SyncController::class, 'importPolaris'])->name('polaris');
    Route::post('/shoutbomb-reports', [SyncController::class, 'importShoutbombReports'])->name('shoutbomb-reports');
    Route::post('/shoutbomb-submissions', [SyncController::class, 'importShoutbombSubmissions'])->name('shoutbomb-submissions');
    Route::post('/ftp-files', [SyncController::class, 'importFTPFiles'])->name('ftp-files');
    Route::post('/ftp-files/stream', [SyncController::class, 'streamImportFTPFiles'])->name('ftp-files.stream');
    Route::post('/ftp-files/cancel', [SyncController::class, 'cancelImportFTPFiles'])->name('ftp-files.cancel');
    Route::post('/shoutbomb-to-logs', [SyncController::class, 'syncShoutbombToLogs'])->name('shoutbomb-to-logs');
    Route::post('/aggregate', [SyncController::class, 'aggregate'])->name('aggregate');
    Route::get('/test-connections', [SyncController::class, 'testConnections'])->name('test-connections');
    Route::get('/logs', [SyncController::class, 'logs'])->name('logs');
    Route::get('/log/{id}', [SyncController::class, 'getLog'])->name('log');
    Route::post('/import-ftp-files', [SyncController::class, 'importFTPFiles']);
});

// Export/Backup routes - Admin only
Route::prefix('export')->name('export.')->group(function () {
    Route::get('/reference-data', [ExportController::class, 'exportReferenceData'])->name('reference-data');
    Route::get('/reference-data-sql', [ExportController::class, 'exportReferenceDataSql'])->name('reference-data-sql');
    Route::post('/notification-data', [ExportController::class, 'exportNotificationData'])->name('notification-data');
    Route::post('/database-backup', [ExportController::class, 'exportDatabaseBackup'])->name('database-backup');
});
