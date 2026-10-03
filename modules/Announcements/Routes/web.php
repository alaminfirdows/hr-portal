<?php

use Illuminate\Support\Facades\Route;
use Modules\Announcements\Http\Controllers\AnnouncementController;

Route::prefix('announcements')->name('announcements.')->group(function () {
    Route::get('/', [AnnouncementController::class, 'index'])->name('index');
    Route::get('manage', [AnnouncementController::class, 'manage'])->name('manage');
    Route::post('/', [AnnouncementController::class, 'store'])->name('store');
    Route::get('{announcement}', [AnnouncementController::class, 'show'])->name('show')->whereNumber('announcement');
    Route::put('{announcement}', [AnnouncementController::class, 'update'])->name('update')->whereNumber('announcement');
    Route::delete('{announcement}', [AnnouncementController::class, 'destroy'])->name('destroy')->whereNumber('announcement');
});
