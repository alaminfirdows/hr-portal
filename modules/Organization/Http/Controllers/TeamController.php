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
use Modules\Organization\Models\Team;

final class TeamController extends OrgUnitController
{
    protected function noun(): string
    {
        return 'team';
    }

    public function index(Request $request): Response
    {
        Gate::authorize('organization.manage');

        $department = $request->integer('department') ?: null;

        return Inertia::render('organization/teams/index', [
            'teams' => Team::query()
                ->with(['department:id,name', 'lead:id,first_name,last_name'])
                ->withCount('employees')
                ->when($department, fn ($q) => $q->where('department_id', $department))
                ->orderBy('name')
                ->get()
                ->map(fn (Team $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'department_id' => $t->department_id,
                    'department' => $t->department->name,
                    'lead_employee_id' => $t->lead_employee_id,
                    'lead' => $t->lead?->full_name,
                    'employees_count' => $t->employees_count,
                ]),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Department $d) => ['value' => $d->id, 'label' => $d->name]),
            'employees' => $this->employeeOptions(),
            'filters' => ['department' => $department],
        ]);
    }

    public function store(Request $request, SaveOrgUnit $save): RedirectResponse
    {
        return $this->persist($request, new Team, $save);
    }

    public function update(Request $request, Team $team, SaveOrgUnit $save): RedirectResponse
    {
        return $this->persist($request, $team, $save);
    }

    public function destroy(Request $request, Team $team, DeleteOrgUnit $delete): RedirectResponse
    {
        return $this->remove($request, $team, $delete);
    }

    protected function rules(Request $request, ?Model $model): array
    {
        return [
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'name' => ['required', 'string', 'max:100', Rule::unique('teams', 'name')
                ->where('department_id', $request->integer('department_id'))
                ->ignore($model)],
            'lead_employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
        ];
    }
}
