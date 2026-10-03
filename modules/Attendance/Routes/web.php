<?php

use Illuminate\Support\Facades\Route;
use Modules\Attendance\Http\Controllers\AttendanceRecordController;
use Modules\Attendance\Http\Controllers\MyAttendanceController;
use Modules\Attendance\Http\Controllers\ShiftController;

Route::prefix('attendance')->name('attendance.')->group(function () {
    Route::get('my', [MyAttendanceController::class, 'index'])->name('my');
    Route::post('check-in', [MyAttendanceController::class, 'checkIn'])->name('check-in');
    Route::post('check-out', [MyAttendanceController::class, 'checkOut'])->name('check-out');

    Route::get('records', [AttendanceRecordController::class, 'index'])->name('records.index');
    Route::post('records', [AttendanceRecordController::class, 'upsert'])->name('records.upsert');

    Route::get('shifts', [ShiftController::class, 'index'])->name('shifts.index');
    Route::post('shifts', [ShiftController::class, 'store'])->name('shifts.store');
    Route::put('shifts/{shift}', [ShiftController::class, 'update'])->name('shifts.update');
    Route::delete('shifts/{shift}', [ShiftController::class, 'destroy'])->name('shifts.destroy');
    Route::post('shifts/{shift}/assign', [ShiftController::class, 'assign'])->name('shifts.assign');
});
