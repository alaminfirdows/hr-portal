<?php

namespace Modules\Payroll\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Employees\Models\Employee;
use Modules\Payroll\Database\Factories\PayslipFactory;

/**
 * @property int $id
 * @property int $payroll_run_id
 * @property int $employee_id
 * @property int $basic
 * @property int $gross
 * @property int $total_deductions
 * @property int $net
 * @property bool $net_clamped
 * @property int $working_days
 * @property float $paid_days
 * @property float $unpaid_days
 * @property int $overtime_minutes
 * @property CarbonImmutable|null $published_at
 * @property-read PayrollRun $run
 * @property-read Employee $employee
 */
#[Fillable([
    'payroll_run_id', 'employee_id', 'basic', 'gross', 'total_deductions', 'net', 'net_clamped',
    'working_days', 'paid_days', 'unpaid_days', 'overtime_minutes', 'published_at',
])]
class Payslip extends Model
{
    /** @use HasFactory<PayslipFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'basic' => 'integer',
            'gross' => 'integer',
            'total_deductions' => 'integer',
            'net' => 'integer',
            'net_clamped' => 'boolean',
            'working_days' => 'integer',
            'paid_days' => 'float',
            'unpaid_days' => 'float',
            'overtime_minutes' => 'integer',
            'published_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return HasMany<PayslipLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PayslipLine::class)->orderBy('sort');
    }
}
