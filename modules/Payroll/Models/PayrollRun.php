<?php

namespace Modules\Payroll\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Identity\Models\User;
use Modules\Payroll\Database\Factories\PayrollRunFactory;
use Modules\Payroll\Enums\PayrollRunStatus;

/**
 * @property int $id
 * @property string $period YYYY-MM
 * @property PayrollRunStatus $status
 * @property int $employee_count
 * @property int $total_gross
 * @property int $total_deductions
 * @property int $total_net
 * @property int $created_by
 * @property int|null $reviewed_by
 * @property int|null $approved_by
 * @property CarbonImmutable|null $calculated_at
 * @property CarbonImmutable|null $processed_at
 * @property-read User $creator
 */
#[Fillable([
    'period', 'status', 'employee_count', 'total_gross', 'total_deductions', 'total_net',
    'created_by', 'reviewed_by', 'approved_by', 'calculated_at', 'processed_at',
])]
class PayrollRun extends Model
{
    /** @use HasFactory<PayrollRunFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => PayrollRunStatus::class,
            'employee_count' => 'integer',
            'total_gross' => 'integer',
            'total_deductions' => 'integer',
            'total_net' => 'integer',
            'calculated_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
        ];
    }

    public function periodStart(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $this->period.'-01') ?? CarbonImmutable::now()->startOfMonth();
    }

    public function periodEnd(): CarbonImmutable
    {
        return $this->periodStart()->endOfMonth()->startOfDay();
    }

    public function label(): string
    {
        return $this->periodStart()->format('F Y');
    }

    /**
     * @return HasMany<Payslip, $this>
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
