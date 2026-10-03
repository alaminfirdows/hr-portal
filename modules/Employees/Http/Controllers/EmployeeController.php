<?php

namespace Modules\Employees\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\AccessControl\Models\Role;
use Modules\AccessControl\Services\Access;
use Modules\Employees\Actions\ChangeEmployeeStatus;
use Modules\Employees\Actions\CreateEmployee;
use Modules\Employees\Actions\InviteEmployee;
use Modules\Employees\Actions\UpdateEmployee;
use Modules\Employees\Enums\EmployeeStatus;
use Modules\Employees\Enums\EmploymentType;
use Modules\Employees\Enums\Gender;
use Modules\Employees\Enums\WorkType;
use Modules\Employees\Http\Requests\EmployeeRequest;
use Modules\Employees\Http\Resources\EmployeePresenter;
use Modules\Employees\Models\Employee;
use Modules\Employees\Models\EmployeeHistory;
use Modules\Identity\Models\User;
use Modules\Organization\Models\Department;
use Modules\Organization\Models\Location;
use Modules\Organization\Models\Position;
use Modules\Organization\Models\Team;

final class EmployeeController extends Controller
{
    public function __construct(private readonly Access $access) {}

    public function index(Request $request): Response
    {
        Gate::authorize('employees.view');

        /** @var User $user */
        $user = $request->user();
        $filters = $request->only(['search', 'status', 'department_id', 'team_id', 'employment_type']);

        $employees = Employee::query()
            ->with(['department:id,name', 'team:id,name', 'position:id,title', 'location:id,name'])
            ->withinIds($this->access->visibleEmployeeIds($user, 'employees.view'))
            ->search($filters['search'] ?? null)
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->where('department_id', $v))
            ->when($filters['team_id'] ?? null, fn ($q, $v) => $q->where('team_id', $v))
            ->when($filters['employment_type'] ?? null, fn ($q, $v) => $q->where('employment_type', $v))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Employee $e) => EmployeePresenter::summary($e));

        return Inertia::render('employees/index', [
            'employees' => $employees,
            'filters' => $filters,
            'options' => [
                'statuses' => EmployeeStatus::options(),
                'employment_types' => EmploymentType::options(),
                'departments' => $this->options(Department::class),
                'teams' => $this->options(Team::class),
            ],
            'can' => ['create' => Gate::allows('employees.create')],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('employees.create');

        return Inertia::render('employees/create', [
            'options' => $this->formOptions(),
            'roles' => Gate::allows('roles.manage')
                ? Role::query()->orderBy('name')->get(['slug', 'name'])->map(fn (Role $r) => ['value' => $r->slug, 'label' => $r->name])
                : [],
            'nextCode' => app(CreateEmployee::class)->nextCode(),
        ]);
    }

    public function store(EmployeeRequest $request, CreateEmployee $create): RedirectResponse
    {
        Gate::authorize('employees.create');

        $roles = Gate::allows('roles.manage') && $request->filled('roles') ? $request->array('roles') : ['employee'];

        $employee = $create->handle($request->attributesForEmployee(), $request->user(), $request->boolean('invite', true), array_values($roles));

        Inertia::flash('toast', ['type' => 'success', 'message' => $employee->user_id
            ? __('Employee created. Invitation sent to :email.', ['email' => $employee->email])
            : __('Employee created.')]);

        return to_route('employees.show', $employee);
    }

    public function show(Request $request, Employee $employee): Response
    {
        Gate::authorize('employees.view', $employee);

        $employee->load(['department:id,name', 'team:id,name', 'position:id,title', 'location:id,name', 'manager:id,first_name,last_name', 'user']);

        return Inertia::render('employees/show', [
            'employee' => EmployeePresenter::full($employee),
            'reports' => $employee->reports()->employed()->orderBy('first_name')->get(['id', 'first_name', 'last_name'])
                ->map(fn (Employee $e) => ['id' => $e->id, 'full_name' => $e->full_name]),
            'history' => $employee->histories()->with('changer:id,name')->limit(50)->get()
                ->map(fn (EmployeeHistory $h) => $this->presentHistory($h)),
            'statuses' => collect($employee->status->allowedTransitions())->map(fn (EmployeeStatus $s) => ['value' => $s->value, 'label' => $s->label()]),
            'can' => [
                'update' => Gate::allows('employees.update', $employee),
                'status' => Gate::allows('employees.status', $employee),
                'invite' => Gate::allows('employees.create') && ! $employee->user?->is_active && $employee->status !== EmployeeStatus::Terminated,
            ],
        ]);
    }

    public function edit(Employee $employee): Response
    {
        Gate::authorize('employees.update', $employee);

        $employee->load(['manager:id,first_name,last_name', 'user']);

        return Inertia::render('employees/edit', [
            'employee' => EmployeePresenter::full($employee),
            'options' => $this->formOptions($employee->id),
        ]);
    }

    public function update(EmployeeRequest $request, Employee $employee, UpdateEmployee $update): RedirectResponse
    {
        Gate::authorize('employees.update', $employee);

        $update->handle(
            $employee,
            $request->attributesForEmployee(),
            $request->user(),
            $request->input('change_reason'),
            $request->date('effective_on'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employee updated.')]);

        return to_route('employees.show', $employee);
    }

    public function status(Request $request, Employee $employee, ChangeEmployeeStatus $change): RedirectResponse
    {
        Gate::authorize('employees.status', $employee);

        $data = $request->validate([
            'status' => ['required', Rule::enum(EmployeeStatus::class)],
            'reason' => ['nullable', 'string', 'max:255'],
            'effective_on' => ['nullable', 'date'],
        ]);

        $change->handle($employee, EmployeeStatus::from($data['status']), $request->user(), $data['reason'] ?? null, $request->date('effective_on'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Status changed to :status.', ['status' => $employee->status->label()])]);

        return back();
    }

    public function invite(Request $request, Employee $employee, InviteEmployee $invite): RedirectResponse
    {
        Gate::authorize('employees.create');

        $invite->handle($employee, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent to :email.', ['email' => $employee->email])]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(?int $exclude = null): array
    {
        return [
            'departments' => $this->options(Department::class),
            'teams' => Team::query()->orderBy('name')->get(['id', 'name', 'department_id'])
                ->map(fn (Team $t) => ['value' => $t->id, 'label' => $t->name, 'department_id' => $t->department_id]),
            'positions' => Position::query()->orderBy('title')->get(['id', 'title'])
                ->map(fn (Position $p) => ['value' => $p->id, 'label' => $p->title]),
            'locations' => $this->options(Location::class),
            'managers' => Employee::query()->employed()->when($exclude, fn ($q) => $q->whereKeyNot($exclude))
                ->orderBy('first_name')->get(['id', 'first_name', 'last_name'])
                ->map(fn (Employee $e) => ['value' => $e->id, 'label' => $e->full_name]),
            'genders' => Gender::options(),
            'employment_types' => EmploymentType::options(),
            'work_types' => WorkType::options(),
        ];
    }

    /**
     * @param  class-string<Department|Team|Location>  $model
     * @return list<array{value: int, label: string}>
     */
    private function options(string $model): array
    {
        return array_values($model::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn ($m) => ['value' => (int) $m->id, 'label' => (string) $m->name])->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function presentHistory(EmployeeHistory $h): array
    {
        return [
            'id' => $h->id,
            'field' => $h->field,
            'field_label' => str($h->field)->replaceLast('_id', '')->headline()->toString(),
            'old' => $this->resolveValue($h->field, $h->old_value),
            'new' => $this->resolveValue($h->field, $h->new_value),
            'reason' => $h->reason,
            'effective_on' => $h->effective_on->toDateString(),
            'changed_by' => $h->changer?->name,
            'created_at' => $h->created_at?->toIso8601String(),
        ];
    }

    private function resolveValue(string $field, ?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($field) {
            'department_id' => Department::query()->whereKey($value)->value('name') ?? "#{$value}",
            'team_id' => Team::query()->whereKey($value)->value('name') ?? "#{$value}",
            'position_id' => Position::query()->whereKey($value)->value('title') ?? "#{$value}",
            'manager_id' => Employee::withTrashed()->find($value)->full_name ?? "#{$value}",
            'status' => EmployeeStatus::tryFrom($value)?->label() ?? $value,
            'employment_type' => EmploymentType::tryFrom($value)?->label() ?? $value,
            default => $value,
        };
    }
}
