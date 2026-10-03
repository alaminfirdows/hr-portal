<?php

namespace Modules\Projects\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Modules\Employees\Models\Employee;
use Modules\Projects\Actions\AddProjectMember;
use Modules\Projects\Actions\RemoveProjectMember;
use Modules\Projects\Actions\UpdateProjectMember;
use Modules\Projects\Enums\MemberRole;
use Modules\Projects\Http\Requests\ProjectMemberRequest;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectMember;
use Modules\Projects\Services\AllocationChecker;

final class ProjectMemberController extends Controller
{
    public function store(ProjectMemberRequest $request, Project $project, AddProjectMember $add, AllocationChecker $allocations): RedirectResponse
    {
        $employee = Employee::query()->findOrFail($request->integer('employee_id'));

        $member = $add->handle(
            $project,
            $employee,
            MemberRole::from($request->string('role')->toString()),
            $request->integer('allocation_percent'),
            $request->user(),
        );

        $this->flashResult($allocations, $member, __('Member added.'));

        return back();
    }

    public function update(ProjectMemberRequest $request, Project $project, ProjectMember $member, UpdateProjectMember $update, AllocationChecker $allocations): RedirectResponse
    {
        $this->ensureBelongs($project, $member);

        $update->handle(
            $member,
            MemberRole::from($request->string('role')->toString()),
            $request->integer('allocation_percent'),
            $request->user(),
        );

        $this->flashResult($allocations, $member, __('Member updated.'));

        return back();
    }

    public function destroy(Request $request, Project $project, ProjectMember $member, RemoveProjectMember $remove): RedirectResponse
    {
        $this->ensureBelongs($project, $member);
        Gate::authorize('projects.view');
        Gate::authorize('view', $project);
        Gate::authorize('manageMembers', [$project, $member->employee_id]);

        $remove->handle($member, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return back();
    }

    private function ensureBelongs(Project $project, ProjectMember $member): void
    {
        abort_unless($member->project_id === $project->id, 404);
    }

    /**
     * Over-allocation never blocks; it only warns.
     */
    private function flashResult(AllocationChecker $allocations, ProjectMember $member, string $success): void
    {
        $total = $allocations->total($member->employee_id);

        if ($total > AllocationChecker::LIMIT) {
            $member->loadMissing('employee');
            Inertia::flash('toast', ['type' => 'warning', 'message' => __(':name is now allocated :total% across active projects (over :limit%).', [
                'name' => $member->employee->full_name,
                'total' => $total,
                'limit' => AllocationChecker::LIMIT,
            ])]);

            return;
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $success]);
    }
}
