<?php

namespace Modules\Attendance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\Http\Requests\ShiftRequest;
use Modules\Attendance\Http\Resources\AttendancePresenter;
use Modules\Attendance\Models\EmployeeShift;
use Modules\Attendance\Models\Shift;
use Modules\Employees\Models\Employee;
use Modules\Shared\Exceptions\DomainException;

/**
 * Shift setup (HR) and assigning employees to shifts. Exactly one active default shift is kept.
 */
final class ShiftController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('shifts.manage');

        $assigned = EmployeeShift::query()->selectRaw('shift_id, count(*) as total')->groupBy('shift_id')->pluck('total', 'shift_id');

        return Inertia::render('attendance/shifts/index', [
            'shifts' => Shift::query()->orderByDesc('is_default')->orderBy('name')->get()->map(fn (Shift $s) => [
                ...AttendancePresenter::shift($s),
                'assigned' => (int) ($assigned[$s->id] ?? 0),
            ]),
            'employees' => Employee::query()->employed()->orderBy('first_name')->orderBy('last_name')->limit(500)->get()
                ->map(fn (Employee $e) => ['value' => $e->id, 'label' => $e->full_name])->all(),
        ]);
    }

    public function store(ShiftRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $shift = Shift::query()->create($this->attributes($request));
            $this->enforceDefault($shift);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Shift created.')]);

        return back();
    }

    public function update(ShiftRequest $request, Shift $shift): RedirectResponse
    {
        DB::transaction(function () use ($request, $shift): void {
            $shift->update($this->attributes($request));
            $this->enforceDefault($shift);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Shift updated.')]);

        return back();
    }

    public function destroy(Shift $shift): RedirectResponse
    {
        Gate::authorize('shifts.manage');

        if ($shift->is_default) {
            throw new DomainException(__('The default shift cannot be deleted. Make another shift the default first.'), 'shift');
        }

        $shift->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Shift deleted.')]);

        return back();
    }

    public function assign(Request $request, Shift $shift): RedirectResponse
    {
        Gate::authorize('shifts.manage');

        $data = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1', 'max:500'],
            'employee_ids.*' => ['integer', Rule::exists('employees', 'id')],
        ]);

        foreach ($data['employee_ids'] as $employeeId) {
            EmployeeShift::query()->updateOrCreate(['employee_id' => $employeeId], ['shift_id' => $shift->id]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employees assigned.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(ShiftRequest $request): array
    {
        $data = $request->validated();
        $data['is_default'] = $request->boolean('is_default');
        $data['is_active'] = $request->boolean('is_active', true);

        if ($data['is_default']) {
            $data['is_active'] = true;
        }

        return $data;
    }

    private function enforceDefault(Shift $shift): void
    {
        if ($shift->is_default) {
            Shift::query()->where('id', '!=', $shift->id)->update(['is_default' => false]);
        }
    }
}
