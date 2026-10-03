<?php

use Illuminate\Support\Facades\Route;
use Modules\Documents\Http\Controllers\DocumentController;
use Modules\Documents\Http\Controllers\DocumentDownloadController;
use Modules\Documents\Http\Controllers\EmployeeDocumentController;
use Modules\Documents\Http\Controllers\FolderController;

Route::prefix('documents')->name('documents.')->group(function () {
    Route::get('/', [DocumentController::class, 'index'])->name('index');
    Route::post('/', [DocumentController::class, 'store'])->name('store');
    Route::post('links', [DocumentController::class, 'storeLink'])->name('links.store');
    Route::post('folders', [FolderController::class, 'store'])->name('folders.store');
    Route::get('employees/{employee}', EmployeeDocumentController::class)->name('employee');

    Route::get('{document}', [DocumentController::class, 'show'])->name('show')->whereNumber('document');
    Route::put('{document}', [DocumentController::class, 'update'])->name('update')->whereNumber('document');
    Route::delete('{document}', [DocumentController::class, 'destroy'])->name('destroy')->whereNumber('document');
    Route::post('{document}/share', [DocumentController::class, 'share'])->name('share')->whereNumber('document');
    Route::get('{document}/download', DocumentDownloadController::class)->name('download')->whereNumber('document');
});
