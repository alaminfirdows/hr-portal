<?php

use Illuminate\Support\Facades\Route;
use Modules\Employees\Http\Controllers\DirectoryController;
use Modules\Employees\Http\Controllers\EmployeeController;
use Modules\Employees\Http\Controllers\OwnProfileController;
use Modules\Employees\Http\Controllers\PhotoController;

Route::get('directory', [DirectoryController::class, 'index'])->name('employees.directory');
Route::get('directory/{employee}', [DirectoryController::class, 'show'])->name('employees.directory.show');

Route::get('my-profile', [OwnProfileController::class, 'edit'])->name('employees.profile.edit');
Route::put('my-profile', [OwnProfileController::class, 'update'])->name('employees.profile.update');

Route::get('employees/{employee}/photo', [PhotoController::class, 'show'])->name('employees.photo');
Route::post('employees/{employee}/photo', [PhotoController::class, 'update'])->name('employees.photo.update');

Route::post('employees/{employee}/status', [EmployeeController::class, 'status'])->name('employees.status');
Route::post('employees/{employee}/invite', [EmployeeController::class, 'invite'])->name('employees.invite');
Route::resource('employees', EmployeeController::class)->except(['destroy']);
