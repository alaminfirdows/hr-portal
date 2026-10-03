<?php

namespace Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Modules\Employees\Models\Employee;
use Modules\Organization\Actions\DeleteOrgUnit;
use Modules\Organization\Actions\SaveOrgUnit;

/**
 * Shared plumbing for the simple org-unit CRUD screens (list + dialog form).
 */
abstract class OrgUnitController extends Controller
{
    protected string $labelAttribute = 'name';

    abstract protected function noun(): string;

    /**
     * @return array<string, mixed>
     */
    abstract protected function rules(Request $request, ?Model $model): array;

    protected function persist(Request $request, Model $model, SaveOrgUnit $save): RedirectResponse
    {
        Gate::authorize('organization.manage');

        $creating = ! $model->exists;
        /** @var array<string, mixed> $data */
        $data = $request->validate($this->rules($request, $creating ? null : $model));
        $save->handle($model, $data, $this->labelAttribute, $request->user()?->getAuthIdentifier());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':noun :state.', [
            'noun' => ucfirst($this->noun()),
            'state' => $creating ? 'created' : 'updated',
        ])]);

        return back();
    }

    protected function remove(Request $request, Model $model, DeleteOrgUnit $delete): RedirectResponse
    {
        Gate::authorize('organization.manage');

        $delete->handle($model, $this->labelAttribute, $request->user()?->getAuthIdentifier());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':noun deleted.', ['noun' => ucfirst($this->noun())])]);

        return back();
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    protected function employeeOptions(): array
    {
        return array_values(Employee::query()
            ->employed()
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn (Employee $e) => ['value' => $e->id, 'label' => $e->full_name])
            ->all());
    }
}
