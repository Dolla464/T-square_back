<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Receptionist Routes — auth:sanctum + role:receptionist
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'role:receptionist'])
    ->prefix('receptionist')
    ->name('receptionist.')
    ->group(function () {

        // TODO: add receptionist routes here

    });
