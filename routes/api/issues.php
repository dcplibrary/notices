<?php

// Issues endpoints
Route::get('/issues', [\Dcplibrary\Notices\Http\Controllers\IssuesController::class, 'issuesApi'])
    ->name('issues.index');
Route::get('/issues/statistics', [\Dcplibrary\Notices\Http\Controllers\IssuesController::class, 'issueStatisticsApi'])
    ->name('issues.statistics');
Route::get('/issues/{id}', [\Dcplibrary\Notices\Http\Controllers\IssuesController::class, 'showIssue'])
    ->whereNumber('id')
    ->name('issues.show');
Route::post('/issues/{id}/resolve', [\Dcplibrary\Notices\Http\Controllers\IssuesController::class, 'resolveIssueApi'])
    ->whereNumber('id')
    ->middleware(['ability:issues:write'])
    ->name('issues.resolve');
Route::post('/issues/bulk-resolve', [\Dcplibrary\Notices\Http\Controllers\IssuesController::class, 'bulkResolveApi'])
    ->middleware(['ability:issues:write'])
    ->name('issues.bulk-resolve');

// Orphaned notifications
Route::get('/orphaned-notifications', [\Dcplibrary\Notices\Http\Controllers\IssuesController::class, 'orphanedNotificationsApi'])
    ->name('orphaned-notifications');
