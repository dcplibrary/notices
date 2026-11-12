<?php

/*
|--------------------------------------------------------------------------
| Settings Routes
|--------------------------------------------------------------------------
|
| System configuration and settings management.
|
*/
Route::prefix('settings')->name('settings.')->group(function () {
    // Settings dashboard
    Route::get('/', [\Dcplibrary\Notices\Http\Controllers\SettingsController::class, 'index'])
        ->name('index');

    // Activity log dashboard (system + user activity)
    Route::get('/activity', [\Dcplibrary\Notices\Http\Controllers\ActivityController::class, 'index'])
        ->name('activity');

    // Maintenance tools (e.g. cleanup import/export logs, backups)
    Route::get('/maintenance', [\Dcplibrary\Notices\Http\Controllers\SettingsMaintenanceBackupController::class, 'maintenance'])
        ->name('maintenance');

    // Detailed cleanup tool view and action
    Route::get('/maintenance/cleanup', [\Dcplibrary\Notices\Http\Controllers\SettingsMaintenanceBackupController::class, 'maintenanceCleanup'])
        ->name('maintenance.cleanup');
    Route::post('/maintenance/cleanup-import-logs', [\Dcplibrary\Notices\Http\Controllers\SettingsMaintenanceBackupController::class, 'runCleanupImportLogs'])
        ->name('maintenance.cleanup-import-logs');

    // Configuration backup & restore
    Route::get('/maintenance/config-backup', [\Dcplibrary\Notices\Http\Controllers\SettingsMaintenanceBackupController::class, 'configBackup'])
        ->name('maintenance.config-backup');
    Route::post('/maintenance/config-backup/create', [\Dcplibrary\Notices\Http\Controllers\SettingsMaintenanceBackupController::class, 'createConfigBackup'])
        ->name('maintenance.config-backup.create');
    Route::post('/maintenance/config-backup/restore-upload', [\Dcplibrary\Notices\Http\Controllers\SettingsMaintenanceBackupController::class, 'restoreConfigFromUpload'])
        ->name('maintenance.config-backup.restore-upload');
    Route::post('/maintenance/config-backup/restore-local', [\Dcplibrary\Notices\Http\Controllers\SettingsMaintenanceBackupController::class, 'restoreConfigFromLocal'])
        ->name('maintenance.config-backup.restore-local');

    // Notices data backup & restore
    Route::get('/maintenance/data-backup', [\Dcplibrary\Notices\Http\Controllers\SettingsMaintenanceBackupController::class, 'dataBackup'])
        ->name('maintenance.data-backup');
    Route::post('/maintenance/data-backup/create', [\Dcplibrary\Notices\Http\Controllers\SettingsMaintenanceBackupController::class, 'createDataBackup'])
        ->name('maintenance.data-backup.create');
    Route::post('/maintenance/data-backup/restore-upload', [\Dcplibrary\Notices\Http\Controllers\SettingsMaintenanceBackupController::class, 'restoreDataFromUpload'])
        ->name('maintenance.data-backup.restore-upload');
    Route::post('/maintenance/data-backup/restore-local', [\Dcplibrary\Notices\Http\Controllers\SettingsMaintenanceBackupController::class, 'restoreDataFromLocal'])
        ->name('maintenance.data-backup.restore-local');

    // Unified backup endpoint (configuration, data, or both)
    Route::post('/maintenance/backup', [\Dcplibrary\Notices\Http\Controllers\SettingsMaintenanceBackupController::class, 'createBackup'])
        ->name('maintenance.backup.create');

    // Legacy combined configuration + data backup (still available if needed)
    Route::post('/maintenance/backup-both', [\Dcplibrary\Notices\Http\Controllers\SettingsMaintenanceBackupController::class, 'createCombinedBackup'])
        ->name('maintenance.backup-both.create');

    // Full backup restore (configuration + data)
    Route::post('/maintenance/backup-full/restore-upload', [\Dcplibrary\Notices\Http\Controllers\SettingsMaintenanceBackupController::class, 'restoreCombinedFromUpload'])
        ->name('maintenance.backup-full.restore-upload');
    Route::post('/maintenance/backup-full/restore-local', [\Dcplibrary\Notices\Http\Controllers\SettingsMaintenanceBackupController::class, 'restoreCombinedFromLocal'])
        ->name('maintenance.backup-full.restore-local');

    // Display settings (lookup table label overrides and visibility)
    Route::get('/display', [\Dcplibrary\Notices\Http\Controllers\SettingsDisplayController::class, 'display'])
        ->name('display');

    // UI Messages (configurable status/help messages)
    Route::get('/messages', [\Dcplibrary\Notices\Http\Controllers\SettingsDisplayController::class, 'messages'])
        ->name('messages');

    Route::post('/messages/update', [\Dcplibrary\Notices\Http\Controllers\SettingsDisplayController::class, 'updateUiMessage'])
        ->name('messages.update');

    Route::post('/messages/status-message', [\Dcplibrary\Notices\Http\Controllers\SettingsDisplayController::class, 'updateStatusMessage'])
        ->name('messages.status-message.update');

    Route::post('/display', [\Dcplibrary\Notices\Http\Controllers\SettingsDisplayController::class, 'storeLookupOverride'])
        ->name('display.store');

    Route::delete('/display/{id}', [\Dcplibrary\Notices\Http\Controllers\SettingsDisplayController::class, 'deleteLookupOverride'])
        ->name('display.delete');

    // AJAX endpoints for display settings
    Route::post('/display/update-label', [\Dcplibrary\Notices\Http\Controllers\SettingsDisplayController::class, 'updateLabel'])
        ->name('display.update-label');

    Route::post('/display/toggle-enabled', [\Dcplibrary\Notices\Http\Controllers\SettingsDisplayController::class, 'toggleEnabled'])
        ->name('display.toggle-enabled');

    Route::post('/display/update-notes', [\Dcplibrary\Notices\Http\Controllers\SettingsDisplayController::class, 'updateNotes'])
        ->name('display.update-notes');

    // API Tokens Management
    Route::get('/api-tokens', [\Dcplibrary\Notices\Http\Controllers\SettingsApiTokensController::class, 'apiTokens'])
        ->name('api-tokens');
    Route::post('/api-tokens/create', [\Dcplibrary\Notices\Http\Controllers\SettingsApiTokensController::class, 'createApiToken'])
        ->name('api-tokens.create');
    Route::delete('/api-tokens/{id}/revoke', [\Dcplibrary\Notices\Http\Controllers\SettingsApiTokensController::class, 'revokeApiToken'])
        ->name('api-tokens.revoke');

    // Jobs (queued imports/maintenance)
    Route::get('/jobs', [\Dcplibrary\Notices\Http\Controllers\SettingsJobsController::class, 'jobs'])
        ->name('jobs');
    Route::get('/jobs/job-runs', [\Dcplibrary\Notices\Http\Controllers\SettingsJobsController::class, 'jobRunsApi'])
        ->name('jobs.job-runs');
    Route::get('/jobs/job-runs/{id}', [\Dcplibrary\Notices\Http\Controllers\SettingsJobsController::class, 'jobRunApi'])
        ->whereNumber('id')
        ->name('jobs.job-runs.show');
    Route::get('/jobs/job-runs/{id}/events', [\Dcplibrary\Notices\Http\Controllers\SettingsJobsController::class, 'jobRunEventsApi'])
        ->whereNumber('id')
        ->name('jobs.job-runs.events');
    Route::post('/jobs/job-runs/{id}/cancel', [\Dcplibrary\Notices\Http\Controllers\SettingsJobsController::class, 'cancelJobRunApi'])
        ->whereNumber('id')
        ->name('jobs.job-runs.cancel');

    // Queue maintenance actions from the web UI (session-auth)
    Route::post('/jobs/actions/import', [\Dcplibrary\Notices\Http\Controllers\SettingsJobsController::class, 'runImportApi'])
        ->name('jobs.actions.import');
    Route::post('/jobs/actions/import-confirmations', [\Dcplibrary\Notices\Http\Controllers\SettingsJobsController::class, 'runImportConfirmationsApi'])
        ->name('jobs.actions.import-confirmations');
    Route::post('/jobs/actions/process-orphaned', [\Dcplibrary\Notices\Http\Controllers\SettingsJobsController::class, 'runProcessOrphanedApi'])
        ->name('jobs.actions.process-orphaned');
    Route::post('/jobs/actions/integrity-check', [\Dcplibrary\Notices\Http\Controllers\SettingsJobsController::class, 'runIntegrityCheckApi'])
        ->name('jobs.actions.integrity-check');
    Route::post('/jobs/actions/cleanup-import-logs', [\Dcplibrary\Notices\Http\Controllers\SettingsJobsController::class, 'runCleanupImportLogsApi'])
        ->name('jobs.actions.cleanup-import-logs');
});
