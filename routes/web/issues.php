<?php

// API endpoint for issue details (used by modal)
Route::get('/api/issues/{id}', [\Dcplibrary\Notices\Http\Controllers\IssuesController::class, 'showIssue'])
    ->name('api.issues.show');

// API endpoint for failed contact details (used by modal)
Route::get('/api/failed-contacts/{id}', [\Dcplibrary\Notices\Http\Controllers\FailedContactsController::class, 'showFailedContact'])
    ->name('api.failed-contacts.show');

// Delivery issues
Route::get('/issues', [\Dcplibrary\Notices\Http\Controllers\IssuesController::class, 'issues'])->name('issues');

Route::get('/issues/orphaned', [\Dcplibrary\Notices\Http\Controllers\IssuesController::class, 'orphaned'])->name('issues.orphaned');

Route::get('/issues/email-system-errors', [\Dcplibrary\Notices\Http\Controllers\IssuesController::class, 'emailSystemErrorClusters'])
    ->name('issues.email-system-errors');

Route::post('/issues/{id}/resolve', [\Dcplibrary\Notices\Http\Controllers\IssuesController::class, 'resolveIssue'])
    ->name('issues.resolve');

Route::get('/issues/bulk-resolve', [\Dcplibrary\Notices\Http\Controllers\IssuesController::class, 'bulkResolveForm'])
    ->name('issues.bulk-resolve-form');

Route::post('/issues/bulk-resolve', [\Dcplibrary\Notices\Http\Controllers\IssuesController::class, 'bulkResolve'])
    ->name('issues.bulk-resolve');

// Affected patrons by failed delivery string
Route::get('/affected-patrons/{id}', [\Dcplibrary\Notices\Http\Controllers\IssuesController::class, 'affectedPatrons'])
    ->name('affected-patrons');

// Resolve an aggregated failed contact
Route::post('/failed-contacts/{id}/resolve', [\Dcplibrary\Notices\Http\Controllers\FailedContactsController::class, 'resolveFailedContact'])
    ->name('failed-contacts.resolve');
