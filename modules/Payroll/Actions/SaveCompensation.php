<?php

namespace Modules\Payroll\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;
use Modules\Payroll\Events\CompensationChanged;
use Modules\Payroll\Models\Compensation;
use Modules\Shared\Exceptions\DomainException;
use Modules\Shared\ValueObjects\Money;

/**
 * Starts a new basic salary. The previous open compensation ends the day before.
 */
final class SaveCompensation
{
    public function handle(Employee $employee, Money $basic, CarbonImmutable $effectiveFrom, ?string $reason, ?User $by = null): Compensation
    {
        $effectiveFrom = $effectiveFrom->startOfDay();
        $latest = null;

        $compensation = DB::transaction(function () use ($employee, $basic, $effectiveFrom, $reason, $by, &$latest): Compensation {
            // Serialise writers per employee; the unique index is the backstop.
            Employee::query()->lockForUpdate()->findOrFail($employee->id);

            $latest = Compensation::query()->where('employee_id', $employee->id)->orderByDesc('effective_from')->first();

            if ($latest !== null && $effectiveFrom->lte($latest->effective_from)) {
                throw new DomainException(__('The effective date must be after :date.', ['date' => $latest->effective_from->toDateString()]), 'effective_from');
            }

            if ($latest !== null && ($latest->effective_to === null || $latest->effective_to->gte($effectiveFrom))) {
                $latest->update(['effective_to' => $effectiveFrom->subDay()]);
            }

            try {
                return Compensation::query()->create([
                    'employee_id' => $employee->id,
                    'basic_salary' => $basic->minor,
                    'effective_from' => $effectiveFrom,
                    'reason' => $reason,
                    'created_by' => $by?->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw new DomainException(__('A salary already starts on this date.'), 'effective_from');
            }
        });

        CompensationChanged::dispatch($employee, $compensation, $latest?->basic_salary, $by?->id);

        return $compensation;
    }
}
