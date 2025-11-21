<?php

use Illuminate\Support\Facades\Route;
use Dcplibrary\notices\App\Http\Controllers\noticesController;

/*
|--------------------------------------------------------------------------
| notices Routes  
|--------------------------------------------------------------------------
*/

Route::group([
    'prefix' => 'notices',
    'middleware' => ['web'],
], function () {
    Route::get('/index', [noticesController::class, 'index'])->name('notices.index');
});
