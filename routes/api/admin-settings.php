<?php

/*
|--------------------------------------------------------------------------
| Admin / Maintenance Actions (write endpoints)
|--------------------------------------------------------------------------
|
| These endpoints run Artisan commands or return operational status.
| Protect with token ability: admin:maintenance (or '*').
|
*/
Route::prefix('admin')->name('admin.')->middleware(['ability:admin:maintenance'])->group(function () {
    // Run the full pipeline (notices:import)
    Route::post('/import', [\Dcplibrary\Notices\Http\Controllers\SettingsJobsController::class, 'runImportApi'])
        ->name('import.run');

    // Run confirmations import (notices:import-confirmations)
    Route::post('/import-confirmations', [\Dcplibrary\Notices\Http\Controllers\SettingsJobsController::class, 'runImportConfirmationsApi'])
        ->name('import-confirmations.run');

    // Run orphaned processing (notices:process-orphaned)
    Route::post('/process-orphaned', [\Dcplibrary\Notices\Http\Controllers\SettingsJobsController::class, 'runProcessOrphanedApi'])
        ->name('process-orphaned.run');

    // Run integrity checks (notices:integrity-check)
    Route::post('/integrity-check', [\Dcplibrary\Notices\Http\Controllers\SettingsJobsController::class, 'runIntegrityCheckApi'])
        ->name('integrity-check.run');

    // Cleanup cached import/export files (notices:cleanup-import-logs)
    Route::post('/cleanup-import-logs', [\Dcplibrary\Notices\Http\Controllers\SettingsJobsController::class, 'runCleanupImportLogsApi'])
        ->name('cleanup-import-logs.run');

    // Job run status
    Route::get('/job-runs', [\Dcplibrary\Notices\Http\Controllers\SettingsJobsController::class, 'jobRunsApi'])
        ->name('job-runs.index');
    Route::get('/job-runs/{id}', [\Dcplibrary\Notices\Http\Controllers\SettingsJobsController::class, 'jobRunApi'])
        ->whereNumber('id')
        ->name('job-runs.show');
    Route::get('/job-runs/{id}/events', [\Dcplibrary\Notices\Http\Controllers\SettingsJobsController::class, 'jobRunEventsApi'])
        ->whereNumber('id')
        ->name('job-runs.events');
    Route::post('/job-runs/{id}/cancel', [\Dcplibrary\Notices\Http\Controllers\SettingsJobsController::class, 'cancelJobRunApi'])
        ->whereNumber('id')
        ->name('job-runs.cancel');

    // Settings APIs (lookup overrides + UI messages)
    Route::get('/lookup-overrides', [\Dcplibrary\Notices\Http\Controllers\SettingsDisplayController::class, 'lookupOverridesApi'])
        ->name('lookup-overrides.index');
    Route::post('/lookup-overrides', [\Dcplibrary\Notices\Http\Controllers\SettingsDisplayController::class, 'upsertLookupOverrideApi'])
        ->name('lookup-overrides.upsert');
    Route::delete('/lookup-overrides/{id}', [\Dcplibrary\Notices\Http\Controllers\SettingsDisplayController::class, 'deleteLookupOverrideApi'])
        ->whereNumber('id')
        ->name('lookup-overrides.delete');

    Route::get('/ui-messages', [\Dcplibrary\Notices\Http\Controllers\SettingsDisplayController::class, 'uiMessagesApi'])
        ->name('ui-messages.index');
    Route::put('/ui-messages/{key}', [\Dcplibrary\Notices\Http\Controllers\SettingsDisplayController::class, 'upsertUiMessageApi'])
        ->name('ui-messages.upsert');
    Route::delete('/ui-messages/{key}', [\Dcplibrary\Notices\Http\Controllers\SettingsDisplayController::class, 'deleteUiMessageApi'])
        ->name('ui-messages.delete');

    // Activity logs (system + user + notification activity)
    Route::get('/activity/notifications', [\Dcplibrary\Notices\Http\Controllers\SettingsActivityController::class, 'notificationActivityLogsApi'])
        ->name('activity.notifications');
    Route::get('/activity/users', [\Dcplibrary\Notices\Http\Controllers\SettingsActivityController::class, 'userActivityLogsApi'])
        ->name('activity.users');
    Route::get('/activity/system', [\Dcplibrary\Notices\Http\Controllers\SettingsActivityController::class, 'systemActivityLogsApi'])
        ->name('activity.system');
});
