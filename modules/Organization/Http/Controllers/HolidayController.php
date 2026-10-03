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
use Modules\Organization\Models\Holiday;

/**
 * Everyone can view the holiday calendar; only organization.manage can change it.
 */
final class HolidayController extends OrgUnitController
{
    protected function noun(): string
    {
        return 'holiday';
    }

    public function index(Request $request): Response
    {
        $year = $request->integer('year') ?: (int) now()->year;

        return Inertia::render('organization/holidays/index', [
            'holidays' => Holiday::query()
                ->whereYear('date', $year)
                ->orderBy('date')
                ->get()
                ->map(fn (Holiday $h) => [
                    'id' => $h->id,
                    'name' => $h->name,
                    'date' => $h->date->toDateString(),
                    'weekday' => $h->date->format('l'),
                    'is_optional' => $h->is_optional,
                    'is_past' => $h->date->isPast(),
                ]),
            'year' => $year,
            'canManage' => Gate::allows('organization.manage'),
        ]);
    }

    public function store(Request $request, SaveOrgUnit $save): RedirectResponse
    {
        return $this->persist($request, new Holiday, $save);
    }

    public function update(Request $request, Holiday $holiday, SaveOrgUnit $save): RedirectResponse
    {
        return $this->persist($request, $holiday, $save);
    }

    public function destroy(Request $request, Holiday $holiday, DeleteOrgUnit $delete): RedirectResponse
    {
        return $this->remove($request, $holiday, $delete);
    }

    protected function rules(Request $request, ?Model $model): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'date' => ['required', 'date', Rule::unique('holidays', 'date')->ignore($model)],
            'is_optional' => ['boolean'],
        ];
    }
}
