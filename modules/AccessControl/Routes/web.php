<?php

use Illuminate\Support\Facades\Route;
use Modules\AccessControl\Http\Controllers\RoleController;

Route::prefix('admin/roles')->name('access.roles.')->group(function () {
    Route::get('/', [RoleController::class, 'index'])->name('index');
    Route::get('create', [RoleController::class, 'create'])->name('create');
    Route::post('/', [RoleController::class, 'store'])->name('store');
    Route::get('{role}/edit', [RoleController::class, 'edit'])->name('edit');
    Route::put('{role}', [RoleController::class, 'update'])->name('update');
    Route::delete('{role}', [RoleController::class, 'destroy'])->name('destroy');
});
