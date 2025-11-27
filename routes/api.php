<?php

use Dcplibrary\Notices\Http\Controllers\Api\AnalyticsController;
use Dcplibrary\Notices\Http\Controllers\Api\NotificationController;
use Dcplibrary\Notices\Http\Controllers\Api\ShoutbombController;
use Dcplibrary\Notices\Http\Controllers\Api\SummaryController;
use Dcplibrary\Notices\Http\Controllers\Api\VerificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| These routes provide RESTful API access to notification data.
| They are prefixed with the route defined in config/notices.php
| (default: api/notices)
|
*/

// Notices
Route::prefix('logs')->name('logs.')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::get('/stats', [NotificationController::class, 'stats'])->name('stats');
    Route::get('/{notification}', [NotificationController::class, 'show'])->name('show');
});

// Summaries
Route::prefix('summaries')->name('summaries.')->group(function () {
    Route::get('/', [SummaryController::class, 'index'])->name('index');
    Route::get('/totals', [SummaryController::class, 'totals'])->name('totals');
    Route::get('/by-type', [SummaryController::class, 'byType'])->name('by-type');
    Route::get('/by-delivery', [SummaryController::class, 'byDelivery'])->name('by-delivery');
    Route::get('/{summary}', [SummaryController::class, 'show'])->name('show');
});

// Analytics
Route::prefix('analytics')->name('analytics.')->group(function () {
    Route::get('/overview', [AnalyticsController::class, 'overview'])->name('overview');
    Route::get('/time-series', [AnalyticsController::class, 'timeSeries'])->name('time-series');
    Route::get('/top-patrons', [AnalyticsController::class, 'topPatrons'])->name('top-patrons');
    Route::get('/success-rate-trend', [AnalyticsController::class, 'successRateTrend'])->name('success-rate-trend');
});

// Shoutbomb
Route::prefix('shoutbomb')->name('shoutbomb.')->group(function () {
    Route::get('/deliveries', [ShoutbombController::class, 'deliveries'])->name('deliveries');
    Route::get('/deliveries/stats', [ShoutbombController::class, 'deliveryStats'])->name('deliveries.stats');

    Route::get('/keyword-usage', [ShoutbombController::class, 'keywordUsage'])->name('keyword-usage');
    Route::get('/keyword-usage/summary', [ShoutbombController::class, 'keywordSummary'])->name('keyword-usage.summary');

    Route::get('/registrations', [ShoutbombController::class, 'registrations'])->name('registrations');
    Route::get('/registrations/latest', [ShoutbombController::class, 'latestRegistration'])->name('registrations.latest');
});

// Verification
Route::prefix('verification')->name('verification.')->group(function () {
    Route::get('/', [VerificationController::class, 'verify'])->name('verify');
    Route::get('/search', [VerificationController::class, 'search'])->name('search');
    Route::get('/patron/{barcode}', [VerificationController::class, 'patron'])->name('patron');
    Route::get('/failures', [VerificationController::class, 'failures'])->name('failures');
    Route::get('/{id}/timeline', [VerificationController::class, 'timeline'])->name('timeline');

    // Troubleshooting routes
    Route::prefix('troubleshooting')->name('troubleshooting.')->group(function () {
        Route::get('/summary', [VerificationController::class, 'troubleshootingSummary'])->name('summary');
        Route::get('/by-reason', [VerificationController::class, 'failuresByReason'])->name('by-reason');
        Route::get('/by-type', [VerificationController::class, 'failuresByType'])->name('by-type');
        Route::get('/mismatches', [VerificationController::class, 'mismatches'])->name('mismatches');
    });
});
