<?php

use Illuminate\Support\Facades\Route;
use Modules\Payroll\Http\Controllers\ComponentController;
use Modules\Payroll\Http\Controllers\EmployeeCompensationController;
use Modules\Payroll\Http\Controllers\PayslipController;
use Modules\Payroll\Http\Controllers\RunController;
use Modules\Payroll\Http\Controllers\TaxSlabController;

Route::prefix('payroll')->name('payroll.')->group(function () {
    Route::resource('components', ComponentController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('tax-slabs', TaxSlabController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('employees', [EmployeeCompensationController::class, 'index'])->name('employees.index');
    Route::get('employees/{employee}', [EmployeeCompensationController::class, 'show'])->name('employees.show');
    Route::post('employees/{employee}/compensations', [EmployeeCompensationController::class, 'storeCompensation'])->name('employees.compensations.store');
    Route::put('employees/{employee}/components', [EmployeeCompensationController::class, 'syncComponents'])->name('employees.components.sync');
    Route::post('employees/{employee}/adjustments', [EmployeeCompensationController::class, 'storeAdjustment'])->name('employees.adjustments.store');
    Route::delete('adjustments/{adjustment}', [EmployeeCompensationController::class, 'destroyAdjustment'])->name('adjustments.destroy');

    Route::get('runs', [RunController::class, 'index'])->name('runs.index');
    Route::post('runs', [RunController::class, 'store'])->name('runs.store');
    Route::get('runs/{run}', [RunController::class, 'show'])->name('runs.show');
    Route::post('runs/{run}/calculate', [RunController::class, 'calculate'])->name('runs.calculate');
    Route::post('runs/{run}/review', [RunController::class, 'review'])->name('runs.review');
    Route::post('runs/{run}/approve', [RunController::class, 'approve'])->name('runs.approve');
    Route::post('runs/{run}/process', [RunController::class, 'process'])->name('runs.process');
    Route::get('runs/{run}/export', [RunController::class, 'export'])->name('runs.export');

    Route::get('payslips', [PayslipController::class, 'index'])->name('payslips.index');
    Route::get('payslips/{payslip}', [PayslipController::class, 'show'])->name('payslips.show');
    Route::get('payslips/{payslip}/pdf', [PayslipController::class, 'pdf'])->name('payslips.pdf');
});
