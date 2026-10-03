<?php

namespace Modules\Leave\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;
use Modules\Leave\Actions\CancelLeaveRequest;
use Modules\Leave\Actions\SubmitLeaveRequest;
use Modules\Leave\Enums\DayPart;
use Modules\Leave\Enums\LeaveStatus;
use Modules\Leave\Http\Requests\SubmitLeaveRequestRequest;
use Modules\Leave\Http\Resources\LeavePresenter;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Services\ApprovalFlow;
use Modules\Leave\Services\LeaveBalanceService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The signed-in employee's own leave: balances, requests, new request, cancel.
 */
final class LeaveRequestController extends Controller
{
    public function __construct(
        private readonly LeaveBalanceService $balances,
        private readonly ApprovalFlow $flow,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('leave.request');

        $employee = $this->employee($request);
        $year = (int) now()->year;

        return Inertia::render('leave/requests/index', [
            'balances' => $this->balances->forEmployee($employee->id, $year),
            'year' => $year,
            'requests' => LeaveRequest::query()
                ->with(['type', 'employee.department'])
                ->where('employee_id', $employee->id)
                ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
                ->latest('start_date')
                ->latest('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (LeaveRequest $r) => LeavePresenter::request($r)),
            'filters' => $request->only(['status']),
            'options' => [
                'statuses' => LeaveStatus::options(),
                'day_parts' => DayPart::options(),
                'types' => LeaveType::query()->active()->orderBy('name')->get()->map(fn (LeaveType $t) => [
                    'value' => $t->id,
                    'label' => $t->name,
                    'allow_half_day' => $t->allow_half_day,
                    'requires_attachment' => $t->requires_attachment,
                ])->all(),
            ],
        ]);
    }

    public function store(SubmitLeaveRequestRequest $request, SubmitLeaveRequest $submit): RedirectResponse
    {
        $user = $this->user($request);

        /** @var array{leave_type_id: int, start_date: string, end_date: string, day_part?: string|null, reason?: string|null} $data */
        $data = $request->safe()->except('attachment');
        $leave = $submit->handle($this->employee($request), [...$data, 'attachment' => $request->file('attachment')], $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => $leave->status === LeaveStatus::Approved
            ? __('Leave approved.')
            : __('Leave request submitted.')]);

        return to_route('leave.requests.show', $leave);
    }

    public function show(Request $request, LeaveRequest $leaveRequest): Response
    {
        Gate::authorize('view', $leaveRequest);

        $leaveRequest->load(['type', 'employee.department', 'approvals.approver']);
        $user = $this->user($request);

        return Inertia::render('leave/requests/show', [
            'leave' => LeavePresenter::request($leaveRequest),
            'approvals' => $leaveRequest->approvals->map(fn ($a) => LeavePresenter::approval($a))->values(),
            'steps' => collect($leaveRequest->steps)->map(fn (string $s, int $i) => [
                'role' => ucfirst($s === 'hr' ? 'HR' : $s),
                'state' => match (true) {
                    $leaveRequest->status === LeaveStatus::Approved || $i < $leaveRequest->current_step => 'done',
                    $leaveRequest->status === LeaveStatus::Pending && $i === $leaveRequest->current_step => 'current',
                    default => 'waiting',
                },
            ])->values(),
            'can' => [
                'act' => $this->flow->canAct($user, $leaveRequest),
                'cancel' => $this->canCancel($user, $leaveRequest),
            ],
        ]);
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest, CancelLeaveRequest $cancel): RedirectResponse
    {
        $cancel->handle($leaveRequest, $this->user($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Leave request cancelled.')]);

        return back();
    }

    public function attachment(LeaveRequest $leaveRequest): StreamedResponse
    {
        Gate::authorize('view', $leaveRequest);

        $path = $leaveRequest->attachment_path;
        $disk = Storage::disk(config('filesystems.default'));

        abort_if($path === null || ! $disk->exists($path), 404);

        return $disk->download($path, 'leave-attachment-'.$leaveRequest->id.'.'.pathinfo($path, PATHINFO_EXTENSION));
    }

    private function canCancel(User $user, LeaveRequest $leave): bool
    {
        $open = $leave->status === LeaveStatus::Pending
            || ($leave->status === LeaveStatus::Approved && $leave->start_date->gte(now()->startOfDay()));

        return $open && ($leave->employee->user_id === $user->id || Gate::forUser($user)->allows('leave.manage', $leave->employee_id));
    }

    private function employee(Request $request): Employee
    {
        return Employee::query()->where('user_id', $this->user($request)->id)->firstOrFail();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
