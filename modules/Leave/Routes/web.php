<?php

use Illuminate\Support\Facades\Route;
use Modules\Leave\Http\Controllers\LeaveApprovalController;
use Modules\Leave\Http\Controllers\LeaveBalanceController;
use Modules\Leave\Http\Controllers\LeaveCalendarController;
use Modules\Leave\Http\Controllers\LeaveRequestController;
use Modules\Leave\Http\Controllers\LeaveTypeController;

Route::prefix('leave')->name('leave.')->group(function () {
    Route::get('requests', [LeaveRequestController::class, 'index'])->name('requests.index');
    Route::post('requests', [LeaveRequestController::class, 'store'])->name('requests.store');
    Route::get('requests/{leaveRequest}', [LeaveRequestController::class, 'show'])->name('requests.show');
    Route::post('requests/{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel'])->name('requests.cancel');
    Route::get('requests/{leaveRequest}/attachment', [LeaveRequestController::class, 'attachment'])->name('requests.attachment');

    Route::get('approvals', [LeaveApprovalController::class, 'index'])->name('approvals.index');
    Route::post('requests/{leaveRequest}/approve', [LeaveApprovalController::class, 'approve'])->name('approvals.approve');
    Route::post('requests/{leaveRequest}/reject', [LeaveApprovalController::class, 'reject'])->name('approvals.reject');

    Route::get('calendar', LeaveCalendarController::class)->name('calendar');

    Route::get('balances', [LeaveBalanceController::class, 'index'])->name('balances.index');
    Route::post('balances/adjust', [LeaveBalanceController::class, 'adjust'])->name('balances.adjust');

    Route::resource('types', LeaveTypeController::class)->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['types' => 'leaveType']);
});
