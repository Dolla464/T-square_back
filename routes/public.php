<?php

use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\PublicWebsiteController;
use App\Http\Controllers\SettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes (No Authentication Required)
|--------------------------------------------------------------------------
*/

Route::get('/home', [HomeController::class, 'index'])
    ->name('home.index');

Route::get('/settings/{key}', [SettingController::class, 'getSettingByKey'])
    ->name('settings.show');
Route::get('/website-media/{key}', [PublicWebsiteController::class, 'getMediaByKey'])
    ->name('website-media.get');
