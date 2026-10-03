<?php

namespace Modules\Leave\Services;

use Modules\AccessControl\Services\Access;
use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;
use Modules\Leave\Enums\ApprovalStepRole;
use Modules\Leave\Enums\LeaveStatus;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Models\LeaveType;
use Modules\Shared\Contracts\OrgHierarchy;
use Modules\Shared\Enums\Scope;

/**
 * Who approves what. Steps come from the leave type; the manager step is dropped when the
 * employee has no manager (or manages themselves). Nobody approves their own request.
 */
final class ApprovalFlow
{
    public function __construct(
        private readonly OrgHierarchy $org,
        private readonly Access $access,
    ) {}

    /**
     * Steps for a new request. Configured steps that all get skipped fall back to HR;
     * a type configured with no steps is auto-approved (empty result).
     *
     * @return list<string>
     */
    public function resolveSteps(Employee $employee, LeaveType $type): array
    {
        $configured = $type->configuredSteps();

        if ($configured === []) {
            return [];
        }

        $hasManager = $employee->manager_id !== null && $employee->manager_id !== $employee->id;
        $steps = [];

        foreach ($configured as $role) {
            if ($role === ApprovalStepRole::Manager && ! $hasManager) {
                continue;
            }

            $steps[] = $role->value;
        }

        return $steps === [] ? [ApprovalStepRole::Hr->value] : $steps;
    }

    public function canAct(User $user, LeaveRequest $request): bool
    {
        if ($request->status !== LeaveStatus::Pending) {
            return false;
        }

        $actor = $this->org->employeeIdForUser($user->id);

        if ($actor !== null && $actor === $request->employee_id) {
            return false;
        }

        $scope = $this->access->scope($user, 'leave.approve');

        if ($scope === null) {
            return false;
        }

        $role = $request->currentRole();

        if ($role === null) {
            return false;
        }

        // HR (scope all) may act on any step.
        if ($scope === Scope::All) {
            return true;
        }

        return $role === ApprovalStepRole::Manager
            && $this->org->managerUserId($request->employee_id) === $user->id;
    }

    /**
     * Users who can act on the request's current step.
     *
     * @return list<int>
     */
    public function approverUserIds(LeaveRequest $request): array
    {
        $role = $request->currentRole();
        $requesterUserId = $this->org->userIdForEmployee($request->employee_id);

        if ($role === ApprovalStepRole::Manager) {
            $manager = $this->org->managerUserId($request->employee_id);

            return $manager !== null && $manager !== $requesterUserId ? [$manager] : [];
        }

        if ($role === null) {
            return [];
        }

        return array_values(User::query()
            ->where('is_active', true)
            ->when($requesterUserId, fn ($q, $id) => $q->whereKeyNot($id))
            ->whereHas('roles', fn ($r) => $r->where('is_super', true)->orWhereHas(
                'permissions',
                fn ($p) => $p->where('permissions.key', 'leave.approve')->where('permission_role.scope', Scope::All->value),
            ))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all());
    }
}
