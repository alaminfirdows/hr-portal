<?php

namespace Modules\Employees\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Employees\Enums\EmployeeStatus;
use Modules\Employees\Events\EmployeeCreated;
use Modules\Employees\Models\Employee;
use Modules\Identity\Actions\InviteUser;
use Modules\Identity\Models\User;

final class CreateEmployee
{
    public function __construct(private readonly InviteUser $invite) {}

    /**
     * @param  array<string, mixed>  $data  validated employee attributes
     * @param  list<string>  $roles  role slugs for the invited account
     */
    public function handle(array $data, ?User $by = null, bool $invite = true, array $roles = ['employee']): Employee
    {
        $data['email'] = Str::lower(trim((string) $data['email']));
        $data['employee_code'] = ($data['employee_code'] ?? null) ?: $this->nextCode();
        $data['status'] = $invite ? EmployeeStatus::Invited : EmployeeStatus::Active;

        $employee = DB::transaction(function () use ($data, $by, $invite, $roles): Employee {
            $employee = Employee::query()->create($data);

            if ($invite) {
                $invitation = $this->invite->handle($employee->full_name, $employee->email, $by, $roles);
                $employee->forceFill(['user_id' => $invitation->user_id])->save();
            }

            return $employee;
        });

        EmployeeCreated::dispatch($employee, $by?->id);

        return $employee;
    }

    public function nextCode(): string
    {
        $last = Employee::withTrashed()->where('employee_code', 'like', 'EMP-%')->orderByDesc('id')->value('employee_code');
        $number = $last ? ((int) Str::after((string) $last, 'EMP-')) + 1 : 1;

        do {
            $code = 'EMP-'.str_pad((string) $number++, 5, '0', STR_PAD_LEFT);
        } while (Employee::withTrashed()->where('employee_code', $code)->exists());

        return $code;
    }
}
