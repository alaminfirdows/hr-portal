<?php

namespace Modules\Employees\Actions;

use Modules\Employees\Events\EmployeeUpdated;
use Modules\Employees\Models\Employee;

/**
 * Self-service fields an employee may change on their own record.
 */
final class UpdateOwnProfile
{
    public const FIELDS = ['phone', 'personal_email', 'address', 'emergency_contact', 'skills', 'links'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Employee $employee, array $data): Employee
    {
        $employee->fill(array_intersect_key($data, array_flip(self::FIELDS)));
        $dirty = $employee->getDirty();

        if ($dirty === []) {
            return $employee;
        }

        $before = array_intersect_key($employee->getOriginal(), $dirty);
        $employee->save();

        EmployeeUpdated::dispatch($employee, $before, array_intersect_key($employee->getAttributes(), $dirty), $employee->user_id);

        return $employee;
    }
}
