<?php

namespace Modules\Leave\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\AccessControl\Services\Access;
use Modules\Identity\Models\User;
use Modules\Leave\Actions\ApproveLeaveRequest;
use Modules\Leave\Actions\RejectLeaveRequest;
use Modules\Leave\Enums\LeaveStatus;
use Modules\Leave\Http\Resources\LeavePresenter;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Services\ApprovalFlow;
use Modules\Organization\Models\Department;
use Modules\Organization\Models\Team;
use Modules\Shared\Contracts\OrgHierarchy;
use Modules\Shared\Support\Filters;

/**
 * Approver inbox. Lists requests inside the viewer's leave.approve scope (never their own);
 * rows flag whether the viewer can act on the current step.
 */
final class LeaveApprovalController extends Controller
{
    public function __construct(
        private readonly Access $access,
        private readonly ApprovalFlow $flow,
        private readonly OrgHierarchy $org,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('leave.approve');

        $user = $this->user($request);
        $me = $this->org->employeeIdForUser($user->id);
        $filters = Filters::only($request, ['status', 'department_id', 'team_id', 'leave_type_id', 'from', 'to']);
        $status = $filters['status'] ?? LeaveStatus::Pending->value;

        $requests = LeaveRequest::query()
            ->with(['type', 'employee.department'])
            ->withinIds($this->access->visibleEmployeeIds($user, 'leave.approve'))
            ->when($me, fn ($q, $id) => $q->where('leave_requests.employee_id', '!=', $id))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($filters['leave_type_id'] ?? null, fn ($q, $v) => $q->where('leave_type_id', $v))
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $v)))
            ->when($filters['team_id'] ?? null, fn ($q, $v) => $q->whereHas('employee', fn ($e) => $e->where('team_id', $v)))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('end_date', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('start_date', '<=', $v))
            ->orderBy('start_date')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (LeaveRequest $r) => [
                ...LeavePresenter::request($r),
                'can_act' => $this->flow->canAct($user, $r),
            ]);

        return Inertia::render('leave/approvals/index', [
            'requests' => $requests,
            'filters' => [...$filters, 'status' => $status],
            'options' => [
                'statuses' => [['value' => 'all', 'label' => 'All'], ...LeaveStatus::options()],
                'departments' => Department::query()->orderBy('name')->get(['id', 'name'])->map(fn ($d) => ['value' => $d->id, 'label' => $d->name])->all(),
                'teams' => Team::query()->orderBy('name')->get(['id', 'name'])->map(fn ($t) => ['value' => $t->id, 'label' => $t->name])->all(),
                'types' => LeaveType::query()->orderBy('name')->get(['id', 'name'])->map(fn ($t) => ['value' => $t->id, 'label' => $t->name])->all(),
            ],
        ]);
    }

    public function approve(Request $request, LeaveRequest $leaveRequest, ApproveLeaveRequest $approve): RedirectResponse
    {
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:500']]);

        $approve->handle($leaveRequest, $this->user($request), $data['comment'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Approval recorded.')]);

        return back();
    }

    public function reject(Request $request, LeaveRequest $leaveRequest, RejectLeaveRequest $reject): RedirectResponse
    {
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:500']]);

        $reject->handle($leaveRequest, $this->user($request), $data['comment'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Request rejected.')]);

        return back();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
