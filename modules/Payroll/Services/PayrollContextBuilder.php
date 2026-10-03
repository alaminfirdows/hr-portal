<?php

namespace Modules\Payroll\Services;

use Illuminate\Support\Collection;
use Modules\Employees\Enums\EmployeeStatus;
use Modules\Employees\Models\Employee;
use Modules\Payroll\Data\AdjustmentSpec;
use Modules\Payroll\Data\ComponentSpec;
use Modules\Payroll\Data\PayrollContext;
use Modules\Payroll\Data\TaxBracket;
use Modules\Payroll\Models\Compensation;
use Modules\Payroll\Models\EmployeePayComponent;
use Modules\Payroll\Models\PayComponent;
use Modules\Payroll\Models\PayrollAdjustment;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\TaxSlab;
use Modules\Shared\Contracts\AttendanceSummary;
use Modules\Shared\Contracts\LeaveCalendar;
use Modules\Shared\Contracts\WorkCalendar;
use Modules\Shared\ValueObjects\Money;

/**
 * Gathers one employee's inputs for a period from the database and the cross-module contracts.
 * Component and slab lookups are memoized per instance (one run).
 */
final class PayrollContextBuilder
{
    /** @var Collection<int, PayComponent>|null */
    private ?Collection $components = null;

    /** @var list<TaxBracket>|null */
    private ?array $brackets = null;

    public function __construct(
        private readonly WorkCalendar $calendar,
        private readonly AttendanceSummary $attendance,
        private readonly LeaveCalendar $leave,
    ) {}

    /**
     * Null when the employee was not employed in the period or has no compensation for it.
     */
    public function build(Employee $employee, PayrollRun $run): ?PayrollContext
    {
        $start = $run->periodStart();
        $end = $run->periodEnd();

        if ($employee->status === EmployeeStatus::Invited || $employee->joined_on->gt($end) || ($employee->exited_on !== null && $employee->exited_on->lt($start))) {
            return null;
        }

        $windowStart = $employee->joined_on->gt($start) ? $employee->joined_on : $start;
        $windowEnd = $employee->exited_on !== null && $employee->exited_on->lt($end) ? $employee->exited_on : $end;

        $compensation = Compensation::query()
            ->where('employee_id', $employee->id)
            ->whereDate('effective_from', '<=', $windowEnd)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $windowStart))
            ->orderByDesc('effective_from')
            ->first();

        if ($compensation === null) {
            return null;
        }

        $attendance = $this->attendance->forPeriod($employee->id, $windowStart, $windowEnd);

        return new PayrollContext(
            monthlyBasic: Money::of($compensation->basic_salary),
            workingDays: $this->calendar->workingDaysBetween($start, $end),
            employedWorkingDays: $this->calendar->workingDaysBetween($windowStart, $windowEnd),
            unpaidLeaveDays: $this->leave->unpaidLeaveDays($employee->id, $windowStart, $windowEnd),
            absentDays: $attendance['absent_days'],
            overtimeMinutes: $attendance['overtime_minutes'],
            yearsOfService: round(abs($employee->joined_on->diffInDays($end)) / 365.25, 2),
            components: $this->componentsFor($employee),
            adjustments: $this->adjustmentsFor($employee, $run->period),
            taxBrackets: $this->brackets(),
        );
    }

    /**
     * @return list<ComponentSpec>
     */
    private function componentsFor(Employee $employee): array
    {
        $this->components ??= PayComponent::query()->where('is_active', true)->orderBy('sort')->orderBy('id')->get();

        $assigned = EmployeePayComponent::query()->where('employee_id', $employee->id)->pluck('amount', 'pay_component_id');

        $specs = [];

        foreach ($this->components as $component) {
            if (! $component->applies_to_all && ! $assigned->has($component->id)) {
                continue;
            }

            $specs[] = new ComponentSpec(
                code: $component->code,
                name: $component->name,
                type: $component->type,
                method: $component->calculation,
                value: $component->value,
                formula: $component->formula,
                taxable: $component->is_taxable,
                override: $assigned->get($component->id),
            );
        }

        return $specs;
    }

    /**
     * @return list<AdjustmentSpec>
     */
    private function adjustmentsFor(Employee $employee, string $period): array
    {
        return array_values(PayrollAdjustment::query()
            ->where('employee_id', $employee->id)
            ->where('period', $period)
            ->orderBy('id')
            ->get()
            ->map(fn (PayrollAdjustment $a) => new AdjustmentSpec($a->name, $a->type, Money::of($a->amount)))
            ->all());
    }

    /**
     * @return list<TaxBracket>
     */
    private function brackets(): array
    {
        return $this->brackets ??= array_values(TaxSlab::query()->orderBy('min_amount')->get()
            ->map(fn (TaxSlab $s) => new TaxBracket($s->min_amount, $s->max_amount, $s->rate_bp))
            ->all());
    }
}
