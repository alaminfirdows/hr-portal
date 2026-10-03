<?php

use Illuminate\Support\Facades\Route;
use Modules\Notifications\Http\Controllers\NotificationController;
use Modules\Notifications\Http\Controllers\PreferenceController;

Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
Route::get('notifications/{id}/open', [NotificationController::class, 'open'])->name('notifications.open');
Route::post('notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
Route::delete('notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

Route::get('settings/notifications', [PreferenceController::class, 'edit'])->name('notifications.preferences.edit');
Route::put('settings/notifications', [PreferenceController::class, 'update'])->name('notifications.preferences.update');
