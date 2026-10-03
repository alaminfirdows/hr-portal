<?php

namespace Modules\Employees\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Employees\Events\EmployeeUpdated;
use Modules\Employees\Models\Employee;
use Modules\Employees\Notifications\ProfileChangedNotification;
use Modules\Employees\Services\HistoryRecorder;
use Modules\Identity\Models\User;
use Modules\Shared\Exceptions\DomainException;

/**
 * HR edit of an employee. Status changes go through ChangeEmployeeStatus.
 */
final class UpdateEmployee
{
    public function __construct(private readonly HistoryRecorder $history) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Employee $employee, array $data, ?User $by = null, ?string $reason = null, ?CarbonInterface $effectiveOn = null): Employee
    {
        unset($data['status'], $data['user_id']);

        if (isset($data['email'])) {
            $data['email'] = Str::lower(trim((string) $data['email']));
        }

        if (isset($data['manager_id']) && (int) $data['manager_id'] === $employee->id) {
            throw new DomainException(__('An employee cannot be their own manager.'), 'manager_id');
        }

        if (! empty($data['manager_id']) && $this->createsCycle($employee->id, (int) $data['manager_id'])) {
            throw new DomainException(__('This manager reports to the employee. Choose another manager.'), 'manager_id');
        }

        $employee->fill($data);
        $dirty = $employee->getDirty();

        if ($dirty === []) {
            return $employee;
        }

        $before = array_intersect_key($employee->getOriginal(), $dirty);
        $after = array_intersect_key($employee->getAttributes(), $dirty);

        DB::transaction(function () use ($employee, $before, $after, $by, $reason, $effectiveOn): void {
            $employee->save();
            $this->history->record($employee, $before, $after, $by?->id, $reason, $effectiveOn);

            if (isset($after['email']) && $employee->user) {
                $employee->user->forceFill(['email' => $employee->email])->save();
            }
        });

        EmployeeUpdated::dispatch($employee, $before, $after, $by?->id);

        if ($employee->user && ! $employee->user->is($by)) {
            $employee->user->notify(new ProfileChangedNotification($employee, array_keys($after)));
        }

        return $employee;
    }

    private function createsCycle(int $employeeId, int $managerId): bool
    {
        $seen = [];
        $current = $managerId;

        while ($current !== null && ! in_array($current, $seen, true)) {
            if ($current === $employeeId) {
                return true;
            }

            $seen[] = $current;
            $next = Employee::query()->whereKey($current)->value('manager_id');
            $current = $next === null ? null : (int) $next;
        }

        return false;
    }
}
