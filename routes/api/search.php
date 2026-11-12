<?php

// Global search
Route::get('/search', [\Dcplibrary\Notices\Http\Controllers\SearchController::class, 'globalSearch'])
    ->name('search');
