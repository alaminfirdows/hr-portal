<?php

use Illuminate\Support\Facades\Route;
use Modules\Settings\Http\Controllers\OrganizationSettingsController;

Route::get('admin/settings', [OrganizationSettingsController::class, 'edit'])->name('settings.organization.edit');
Route::put('admin/settings', [OrganizationSettingsController::class, 'update'])->name('settings.organization.update');
