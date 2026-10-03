<?php

use Illuminate\Support\Facades\Route;
use Modules\Organization\Http\Controllers\DepartmentController;
use Modules\Organization\Http\Controllers\HolidayController;
use Modules\Organization\Http\Controllers\LocationController;
use Modules\Organization\Http\Controllers\PositionController;
use Modules\Organization\Http\Controllers\TeamController;

Route::prefix('organization')->name('organization.')->group(function () {
    Route::resource('departments', DepartmentController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('teams', TeamController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('positions', PositionController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('locations', LocationController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('holidays', HolidayController::class)->only(['index', 'store', 'update', 'destroy']);
});
