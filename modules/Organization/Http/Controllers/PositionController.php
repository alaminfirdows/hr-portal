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
use Modules\Organization\Models\Position;

final class PositionController extends OrgUnitController
{
    protected string $labelAttribute = 'title';

    protected function noun(): string
    {
        return 'position';
    }

    public function index(): Response
    {
        Gate::authorize('organization.manage');

        return Inertia::render('organization/positions/index', [
            'positions' => Position::query()
                ->with('department:id,name')
                ->withCount('employees')
                ->orderBy('title')
                ->get()
                ->map(fn (Position $p) => [
                    'id' => $p->id,
                    'title' => $p->title,
                    'level' => $p->level,
                    'department_id' => $p->department_id,
                    'department' => $p->department?->name,
                    'employees_count' => $p->employees_count,
                ]),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Department $d) => ['value' => $d->id, 'label' => $d->name]),
        ]);
    }

    public function store(Request $request, SaveOrgUnit $save): RedirectResponse
    {
        return $this->persist($request, new Position, $save);
    }

    public function update(Request $request, Position $position, SaveOrgUnit $save): RedirectResponse
    {
        return $this->persist($request, $position, $save);
    }

    public function destroy(Request $request, Position $position, DeleteOrgUnit $delete): RedirectResponse
    {
        return $this->remove($request, $position, $delete);
    }

    protected function rules(Request $request, ?Model $model): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'level' => ['nullable', 'string', 'max:50'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
        ];
    }
}
