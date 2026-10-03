<?php

namespace Modules\Attendance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\AccessControl\Services\Access;
use Modules\Attendance\Actions\UpsertAttendanceRecord;
use Modules\Attendance\Enums\AttendanceStatus;
use Modules\Attendance\Http\Requests\UpsertAttendanceRequest;
use Modules\Attendance\Http\Resources\AttendancePresenter;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;
use Modules\Organization\Models\Department;

/**
 * Team / company attendance inside the viewer's attendance.view scope, with manual corrections.
 */
final class AttendanceRecordController extends Controller
{
    public function __construct(private readonly Access $access) {}

    public function index(Request $request): Response
    {
        Gate::authorize('attendance.view');

        $user = $this->user($request);
        $filters = $request->only(['from', 'to', 'status', 'department_id', 'search']);
        $from = $this->date($filters['from'] ?? null) ?? now()->toDateString();
        $to = $this->date($filters['to'] ?? null) ?? $from;
        $ids = $this->access->visibleEmployeeIds($user, 'attendance.view');

        $base = AttendanceRecord::query()
            ->withinIds($ids)
            ->betweenDates($from, $to)
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $v)))
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->whereHas('employee', fn ($e) => $e->search($v)));

        $counts = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $records = $base
            ->with('employee.department')
            ->orderByDesc('date')
            ->orderBy('employee_id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (AttendanceRecord $r) => [
                ...AttendancePresenter::record($r),
                'employee' => ['id' => $r->employee->id, 'full_name' => $r->employee->full_name, 'department' => $r->employee->department?->name],
            ]);

        $manageIds = $this->access->visibleEmployeeIds($user, 'attendance.manage');
        $canManage = $this->access->scope($user, 'attendance.manage') !== null;

        return Inertia::render('attendance/records/index', [
            'records' => $records,
            'filters' => [...$filters, 'from' => $from, 'to' => $to],
            'counts' => collect(AttendanceStatus::cases())->map(fn (AttendanceStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'total' => (int) ($counts[$s->value] ?? 0),
            ])->values(),
            'can_manage' => $canManage,
            'options' => [
                'statuses' => AttendanceStatus::options(),
                'departments' => Department::query()->orderBy('name')->get(['id', 'name'])->map(fn ($d) => ['value' => $d->id, 'label' => $d->name])->all(),
                'employees' => $canManage
                    ? Employee::query()->employed()->withinIds($manageIds)->orderBy('first_name')->orderBy('last_name')->limit(500)->get()
                        ->map(fn (Employee $e) => ['value' => $e->id, 'label' => $e->full_name])->all()
                    : [],
            ],
        ]);
    }

    public function upsert(UpsertAttendanceRequest $request, UpsertAttendanceRecord $upsert): RedirectResponse
    {
        /** @var array{employee_id: int, date: string, check_in?: string|null, check_out?: string|null, status?: string|null, note?: string|null} $data */
        $data = $request->validated();

        $upsert->handle((int) $data['employee_id'], $data['date'], $data, $this->user($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Attendance saved.')]);

        return back();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function date(?string $value): ?string
    {
        return $value !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }
}
