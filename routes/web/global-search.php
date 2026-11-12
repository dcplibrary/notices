<?php

Route::get('/notices/global-search', [\Dcplibrary\Notices\Http\Controllers\SearchController::class, 'globalSearch'])
    ->name('notices.global-search');
