<?php

namespace Modules\Employees\Http\Resources;

use Modules\Employees\Models\Employee;

/**
 * Array shapes sent to pages. Directory shape exposes public fields only.
 */
final class EmployeePresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function summary(Employee $e): array
    {
        return [
            'id' => $e->id,
            'employee_code' => $e->employee_code,
            'full_name' => $e->full_name,
            'email' => $e->email,
            'photo_url' => $e->photoUrl(),
            'department' => $e->department?->name,
            'team' => $e->team?->name,
            'position' => $e->position?->title,
            'location' => $e->location?->name,
            'status' => $e->status->value,
            'status_label' => $e->status->label(),
            'joined_on' => $e->joined_on->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function directory(Employee $e): array
    {
        return [
            'id' => $e->id,
            'full_name' => $e->full_name,
            'email' => $e->email,
            'phone' => $e->phone,
            'photo_url' => $e->photoUrl(),
            'department' => $e->department?->name,
            'team' => $e->team?->name,
            'position' => $e->position?->title,
            'location' => $e->location?->name,
            'manager' => $e->manager ? ['id' => $e->manager->id, 'full_name' => $e->manager->full_name] : null,
            'work_type' => $e->work_type->label(),
            'skills' => $e->skills ?? [],
            'links' => $e->links ?? [],
            'status' => $e->status->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function full(Employee $e): array
    {
        return [
            ...self::summary($e),
            'first_name' => $e->first_name,
            'last_name' => $e->last_name,
            'personal_email' => $e->personal_email,
            'phone' => $e->phone,
            'date_of_birth' => $e->date_of_birth?->toDateString(),
            'gender' => $e->gender?->value,
            'department_id' => $e->department_id,
            'team_id' => $e->team_id,
            'position_id' => $e->position_id,
            'location_id' => $e->location_id,
            'manager_id' => $e->manager_id,
            'manager' => $e->manager ? ['id' => $e->manager->id, 'full_name' => $e->manager->full_name] : null,
            'exited_on' => $e->exited_on?->toDateString(),
            'employment_type' => $e->employment_type->value,
            'work_type' => $e->work_type->value,
            'address' => $e->address,
            'emergency_contact' => $e->emergency_contact ?? ['name' => '', 'relation' => '', 'phone' => ''],
            'skills' => $e->skills ?? [],
            'links' => $e->links ?? [],
            'user' => $e->user ? [
                'id' => $e->user->id,
                'is_active' => $e->user->is_active,
                'last_login_at' => $e->user->last_login_at?->toIso8601String(),
            ] : null,
        ];
    }
}
