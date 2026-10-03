<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Http\Controllers\SessionController;
use Modules\Identity\Http\Controllers\UserController;

Route::prefix('admin/users')->name('identity.users.')->group(function () {
    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::post('/', [UserController::class, 'store'])->name('store');
    Route::post('{user}/resend', [UserController::class, 'resend'])->name('resend');
    Route::put('{user}/roles', [UserController::class, 'roles'])->name('roles');
    Route::post('{user}/activate', [UserController::class, 'activate'])->name('activate');
    Route::post('{user}/deactivate', [UserController::class, 'deactivate'])->name('deactivate');
});

Route::get('settings/sessions', [SessionController::class, 'index'])->name('identity.sessions.index');
Route::delete('settings/sessions', [SessionController::class, 'destroy'])
    ->middleware('password.confirm')
    ->name('identity.sessions.destroy');
