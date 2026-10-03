<?php

namespace Modules\Employees\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Employees\Http\Resources\EmployeePresenter;
use Modules\Employees\Models\Employee;
use Modules\Organization\Models\Department;
use Modules\Organization\Models\Location;
use Modules\Organization\Models\Team;
use Modules\Shared\Support\Filters;

final class DirectoryController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('directory.view');

        $filters = Filters::only($request, ['search', 'department_id', 'team_id', 'location_id']);

        return Inertia::render('employees/directory/index', [
            'employees' => Employee::query()
                ->with(['department:id,name', 'team:id,name', 'position:id,title', 'location:id,name', 'manager:id,first_name,last_name'])
                ->employed()
                ->search($filters['search'] ?? null)
                ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->where('department_id', $v))
                ->when($filters['team_id'] ?? null, fn ($q, $v) => $q->where('team_id', $v))
                ->when($filters['location_id'] ?? null, fn ($q, $v) => $q->where('location_id', $v))
                ->orderBy('first_name')
                ->paginate(24)
                ->withQueryString()
                ->through(fn (Employee $e) => EmployeePresenter::directory($e)),
            'filters' => $filters,
            'options' => [
                'departments' => Department::query()->orderBy('name')->get(['id', 'name'])->map(fn ($d) => ['value' => $d->id, 'label' => $d->name]),
                'teams' => Team::query()->orderBy('name')->get(['id', 'name'])->map(fn ($t) => ['value' => $t->id, 'label' => $t->name]),
                'locations' => Location::query()->orderBy('name')->get(['id', 'name'])->map(fn ($l) => ['value' => $l->id, 'label' => $l->name]),
            ],
        ]);
    }

    public function show(Employee $employee): Response
    {
        Gate::authorize('directory.view');

        abort_unless($employee->status->isEmployed(), 404);

        $employee->load(['department:id,name', 'team:id,name', 'position:id,title', 'location:id,name', 'manager:id,first_name,last_name']);

        return Inertia::render('employees/directory/show', [
            'employee' => EmployeePresenter::directory($employee),
            'reports' => $employee->reports()->employed()->with('position:id,title')->orderBy('first_name')->get()
                ->map(fn (Employee $e) => ['id' => $e->id, 'full_name' => $e->full_name, 'position' => $e->position?->title, 'photo_url' => $e->photoUrl()]),
            'canViewRecord' => Gate::allows('employees.view', $employee),
        ]);
    }
}
