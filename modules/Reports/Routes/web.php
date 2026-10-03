<?php

use Illuminate\Support\Facades\Route;
use Modules\Reports\Http\Controllers\ReportController;

Route::prefix('reports')->name('reports.')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->name('index');
    Route::get('{key}', [ReportController::class, 'show'])->name('show');
    Route::get('{key}/export', [ReportController::class, 'export'])->name('export');
});
