<?php

// Data viewing routes
Route::prefix('data')->name('data.')->group(function () {
    // Redirect /data to report-emails by default
    Route::get('/', function () {
        return redirect()->route('notices.data.report-emails');
    });

    // Unified data search (emails + export batches)
    Route::get('/search', [\Dcplibrary\Notices\Http\Controllers\DataController::class, 'unifiedSearch'])->name('search');

    // Report Emails (LX Starter email reports)
    Route::get('/report-emails', [\Dcplibrary\Notices\Http\Controllers\DataController::class, 'reportEmails'])->name('report-emails');
    Route::get('/report-emails/{id}', [\Dcplibrary\Notices\Http\Controllers\DataController::class, 'reportEmailDetail'])->name('report-email-detail');

    // Export Files (Polaris FTP exports)
    Route::get('/export-files', [\Dcplibrary\Notices\Http\Controllers\DataController::class, 'exportFiles'])->name('export-files');

    // Notification Log viewer
    Route::get('/notification-log', \Dcplibrary\Notices\Http\Controllers\NotificationLogViewerController::class)->name('notification-log');

    // PhoneNotices (voice/SMS validation) viewer
    Route::get('/phone-notices', \Dcplibrary\Notices\Http\Controllers\PhoneNoticesController::class)->name('phone-notices');

    // NotificationHistory (Polaris) viewer
    Route::get('/notification-history', \Dcplibrary\Notices\Http\Controllers\NotificationHistoryController::class)->name('notification-history');

    // Activity Files (normalized activity sources linked to files/lines)
    Route::get('/activity-files/{id}', [\Dcplibrary\Notices\Http\Controllers\DataController::class, 'activityFileDetail'])
        ->name('activity-file-detail');

    // Legacy route - redirect to report-emails
    Route::get('/emails', function () {
        return redirect()->route('notices.data.report-emails');
    })->name('emails');
    Route::get('/emails/{id}', [\Dcplibrary\Notices\Http\Controllers\DataController::class, 'reportEmailDetail'])->name('email-detail');
});
