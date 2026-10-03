<?php

namespace Modules\Organization\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Organization\Actions\DeleteOrgUnit;
use Modules\Organization\Actions\SaveOrgUnit;
use Modules\Organization\Models\Department;

final class DepartmentController extends OrgUnitController
{
    protected function noun(): string
    {
        return 'department';
    }

    public function index(Request $request): Response
    {
        Gate::authorize('organization.manage');

        return Inertia::render('organization/departments/index', [
            'departments' => Department::query()
                ->with('head:id,first_name,last_name')
                ->withCount(['teams', 'employees'])
                ->orderBy('name')
                ->get()
                ->map(fn (Department $d) => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'code' => $d->code,
                    'description' => $d->description,
                    'head_employee_id' => $d->head_employee_id,
                    'head' => $d->head?->full_name,
                    'teams_count' => $d->teams_count,
                    'employees_count' => $d->employees_count,
                ]),
            'employees' => $this->employeeOptions(),
        ]);
    }

    public function store(Request $request, SaveOrgUnit $save): RedirectResponse
    {
        return $this->persist($request, new Department, $save);
    }

    public function update(Request $request, Department $department, SaveOrgUnit $save): RedirectResponse
    {
        return $this->persist($request, $department, $save);
    }

    public function destroy(Request $request, Department $department, DeleteOrgUnit $delete): RedirectResponse
    {
        return $this->remove($request, $department, $delete);
    }

    protected function rules(Request $request, ?Model $model): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('departments', 'code')->ignore($model)],
            'description' => ['nullable', 'string', 'max:1000'],
            'head_employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
        ];
    }
}
