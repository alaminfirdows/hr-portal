<?php

namespace Modules\Employees\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Employees\Enums\EmploymentType;
use Modules\Employees\Enums\Gender;
use Modules\Employees\Enums\WorkType;
use Modules\Employees\Models\Employee;

final class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Employee|null $employee */
        $employee = $this->route('employee');

        return $employee instanceof Employee
            ? (bool) $this->user()?->can('employees.update', $employee)
            : (bool) $this->user()?->can('employees.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee|null $employee */
        $employee = $this->route('employee');

        return [
            'employee_code' => ['nullable', 'string', 'max:30', Rule::unique('employees', 'employee_code')->ignore($employee)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('employees', 'email')->ignore($employee),
                Rule::unique('users', 'email')->ignore($employee?->user_id)],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')->where(fn ($q) => $this->input('department_id') ? $q->where('department_id', $this->input('department_id')) : $q)],
            'position_id' => ['nullable', 'integer', Rule::exists('positions', 'id')],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
            'manager_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'joined_on' => ['required', 'date'],
            'employment_type' => ['required', Rule::enum(EmploymentType::class)],
            'work_type' => ['required', Rule::enum(WorkType::class)],
            'address' => ['nullable', 'string', 'max:1000'],
            'emergency_contact' => ['nullable', 'array'],
            'emergency_contact.name' => ['nullable', 'string', 'max:100'],
            'emergency_contact.relation' => ['nullable', 'string', 'max:50'],
            'emergency_contact.phone' => ['nullable', 'string', 'max:50'],
            'skills' => ['nullable', 'array', 'max:30'],
            'skills.*' => ['string', 'max:50'],
            'links' => ['nullable', 'array', 'max:10'],
            'links.*' => ['nullable', 'url', 'max:255'],
            // create only
            'invite' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'slug')],
            // update only
            'change_reason' => ['nullable', 'string', 'max:255'],
            'effective_on' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function attributesForEmployee(): array
    {
        return collect($this->validated())->except(['invite', 'roles', 'change_reason', 'effective_on'])->all();
    }

    public function messages(): array
    {
        return ['team_id.exists' => __('The team must belong to the selected department.')];
    }
}
