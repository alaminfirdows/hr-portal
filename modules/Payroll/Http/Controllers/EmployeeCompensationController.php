<?php

namespace Modules\Payroll\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\AccessControl\Services\Access;
use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;
use Modules\Payroll\Actions\SaveAdjustment;
use Modules\Payroll\Actions\SaveCompensation;
use Modules\Payroll\Actions\SyncEmployeeComponents;
use Modules\Payroll\Enums\ComponentType;
use Modules\Payroll\Http\Resources\PayrollPresenter;
use Modules\Payroll\Models\Compensation;
use Modules\Payroll\Models\EmployeePayComponent;
use Modules\Payroll\Models\PayComponent;
use Modules\Payroll\Models\PayrollAdjustment;
use Modules\Shared\ValueObjects\Money;

/**
 * Per-employee payroll setup: salary history, assigned components, one-off adjustments.
 */
final class EmployeeCompensationController extends Controller
{
    public function __construct(private readonly Access $access) {}

    public function index(Request $request): Response
    {
        Gate::authorize('payroll.manage');

        /** @var User $user */
        $user = $request->user();
        $search = $request->string('search')->toString();

        $employees = Employee::query()
            ->employed()
            ->withinIds($this->access->visibleEmployeeIds($user, 'payroll.manage'))
            ->search($search)
            ->with('position:id,title')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate(20)
            ->withQueryString();

        $current = Compensation::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereNull('effective_to')
            ->pluck('basic_salary', 'employee_id');

        return Inertia::render('payroll/employees/index', [
            'employees' => $employees->through(fn (Employee $e) => [
                'id' => $e->id,
                'full_name' => $e->full_name,
                'employee_code' => $e->employee_code,
                'position' => $e->position?->title,
                'basic_salary' => $current->get($e->id),
            ]),
            'filters' => ['search' => $search],
            'currency' => PayrollPresenter::currency(),
        ]);
    }

    public function show(Employee $employee): Response
    {
        Gate::authorize('payroll.manage', $employee);

        $assigned = EmployeePayComponent::query()->where('employee_id', $employee->id)->pluck('amount', 'pay_component_id');

        return Inertia::render('payroll/employees/show', [
            'employee' => ['id' => $employee->id, 'full_name' => $employee->full_name, 'employee_code' => $employee->employee_code, 'joined_on' => $employee->joined_on->toDateString()],
            'compensations' => Compensation::query()->where('employee_id', $employee->id)
                ->orderByDesc('effective_from')->get()
                ->map(fn (Compensation $c) => [
                    'id' => $c->id,
                    'basic_salary' => $c->basic_salary,
                    'effective_from' => $c->effective_from->toDateString(),
                    'effective_to' => $c->effective_to?->toDateString(),
                    'reason' => $c->reason,
                ]),
            'components' => PayComponent::query()->where('is_active', true)->orderBy('sort')->orderBy('id')->get()
                ->map(fn (PayComponent $c) => [
                    ...PayrollPresenter::component($c),
                    'assigned' => $c->applies_to_all || $assigned->has($c->id),
                    'direct' => $assigned->has($c->id),
                    'override' => PayrollPresenter::decimal($assigned->get($c->id)),
                ]),
            'adjustments' => PayrollAdjustment::query()->where('employee_id', $employee->id)
                ->orderByDesc('period')->orderByDesc('id')->limit(24)->get()
                ->map(fn (PayrollAdjustment $a) => [
                    'id' => $a->id,
                    'period' => $a->period,
                    'type' => $a->type->value,
                    'type_label' => $a->type->label(),
                    'type_color' => $a->type->color(),
                    'name' => $a->name,
                    'amount' => $a->amount,
                    'note' => $a->note,
                ]),
            'options' => ['types' => ComponentType::options()],
            'currency' => PayrollPresenter::currency(),
            'defaultPeriod' => now()->format('Y-m'),
        ]);
    }

    public function storeCompensation(Request $request, Employee $employee, SaveCompensation $save): RedirectResponse
    {
        Gate::authorize('payroll.manage', $employee);

        $data = $request->validate([
            'basic_salary' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'effective_from' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $save->handle(
            $employee,
            Money::fromDecimal($data['basic_salary']),
            CarbonImmutable::parse($data['effective_from']),
            $data['reason'] ?? null,
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Salary updated.')]);

        return back();
    }

    public function syncComponents(Request $request, Employee $employee, SyncEmployeeComponents $sync): RedirectResponse
    {
        Gate::authorize('payroll.manage', $employee);

        $data = $request->validate([
            'components' => ['present', 'array'],
            'components.*.id' => ['required', 'integer', Rule::exists('pay_components', 'id')],
            'components.*.amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ]);

        $assignments = [];
        foreach ($data['components'] as $row) {
            $assignments[(int) $row['id']] = isset($row['amount']) && $row['amount'] !== '' ? Money::fromDecimal($row['amount'])->minor : null;
        }

        $sync->handle($employee, $assignments, $request->user()?->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Components updated.')]);

        return back();
    }

    public function storeAdjustment(Request $request, Employee $employee, SaveAdjustment $save): RedirectResponse
    {
        Gate::authorize('payroll.manage', $employee);

        $data = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'type' => ['required', Rule::enum(ComponentType::class)],
            'name' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $save->handle([
            'employee_id' => $employee->id,
            'period' => $data['period'],
            'type' => $data['type'],
            'name' => $data['name'],
            'amount' => Money::fromDecimal($data['amount'])->minor,
            'note' => $data['note'] ?? null,
        ], $request->user()?->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Adjustment added.')]);

        return back();
    }

    public function destroyAdjustment(Request $request, PayrollAdjustment $adjustment, SaveAdjustment $save): RedirectResponse
    {
        Gate::authorize('payroll.manage', $adjustment->employee);

        $save->delete($adjustment, $request->user()?->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Adjustment removed.')]);

        return back();
    }
}
