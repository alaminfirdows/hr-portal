<?php

use Illuminate\Support\Facades\Route;
use Modules\Audit\Http\Controllers\AuditLogController;

Route::get('admin/audit', [AuditLogController::class, 'index'])->name('audit.index');
