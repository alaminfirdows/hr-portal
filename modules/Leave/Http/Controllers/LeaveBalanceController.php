<?php

namespace Modules\Leave\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\AccessControl\Services\Access;
use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;
use Modules\Leave\Actions\AdjustBalance;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Services\LeaveBalanceService;

/**
 * HR view of everyone's balances with manual adjustments.
 */
final class LeaveBalanceController extends Controller
{
    public function __construct(
        private readonly Access $access,
        private readonly LeaveBalanceService $balances,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('leave.manage');

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $year = $request->integer('year') ?: (int) now()->year;
        $search = $request->string('search')->toString();

        $employees = Employee::query()
            ->employed()
            ->withinIds($this->access->visibleEmployeeIds($user, 'leave.manage'))
            ->search($search)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Employee $e) => [
                'id' => $e->id,
                'full_name' => $e->full_name,
                'balances' => $this->balances->forEmployee($e->id, $year),
            ]);

        return Inertia::render('leave/balances/index', [
            'employees' => $employees,
            'year' => $year,
            'filters' => ['search' => $search],
            'types' => LeaveType::query()->active()->where('annual_quota', '!=', null)->orderBy('name')->get(['id', 'name'])
                ->map(fn (LeaveType $t) => ['value' => $t->id, 'label' => $t->name])->all(),
        ]);
    }

    public function adjust(Request $request, AdjustBalance $adjust): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'leave_type_id' => ['required', 'integer', Rule::exists('leave_types', 'id')],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'delta' => ['required', 'numeric', 'between:-999,999'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $employee = Employee::query()->findOrFail((int) $data['employee_id']);
        Gate::authorize('leave.manage', $employee);

        $adjust->handle(
            $employee,
            LeaveType::query()->findOrFail((int) $data['leave_type_id']),
            (int) $data['year'],
            (float) $data['delta'],
            $data['reason'],
            $request->user() instanceof User ? $request->user() : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Balance adjusted.')]);

        return back();
    }
}
