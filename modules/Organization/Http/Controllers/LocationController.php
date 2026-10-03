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
use Modules\Organization\Models\Location;

final class LocationController extends OrgUnitController
{
    protected function noun(): string
    {
        return 'location';
    }

    public function index(): Response
    {
        Gate::authorize('organization.manage');

        return Inertia::render('organization/locations/index', [
            'locations' => Location::query()
                ->withCount('employees')
                ->orderBy('name')
                ->get()
                ->map(fn (Location $l) => [
                    'id' => $l->id,
                    'name' => $l->name,
                    'address' => $l->address,
                    'timezone' => $l->timezone,
                    'employees_count' => $l->employees_count,
                ]),
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function store(Request $request, SaveOrgUnit $save): RedirectResponse
    {
        return $this->persist($request, new Location, $save);
    }

    public function update(Request $request, Location $location, SaveOrgUnit $save): RedirectResponse
    {
        return $this->persist($request, $location, $save);
    }

    public function destroy(Request $request, Location $location, DeleteOrgUnit $delete): RedirectResponse
    {
        return $this->remove($request, $location, $delete);
    }

    protected function rules(Request $request, ?Model $model): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('locations', 'name')->ignore($model)],
            'address' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'timezone:all'],
        ];
    }
}
