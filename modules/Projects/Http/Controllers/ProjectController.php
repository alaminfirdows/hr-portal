<?php

namespace Modules\Projects\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\AccessControl\Services\Access;
use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;
use Modules\Projects\Actions\ArchiveProject;
use Modules\Projects\Actions\CreateProject;
use Modules\Projects\Actions\UpdateProject;
use Modules\Projects\Enums\MemberRole;
use Modules\Projects\Enums\ProjectPriority;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Enums\ProjectVisibility;
use Modules\Projects\Http\Requests\ProjectRequest;
use Modules\Projects\Http\Resources\ProjectPresenter;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectMember;
use Modules\Projects\Services\AllocationChecker;
use Modules\Projects\Services\CustomFields;
use Modules\Projects\Services\ProjectAccess;
use Modules\Shared\Contracts\AuditTrail;
use Modules\Shared\Contracts\OrgHierarchy;
use Modules\Shared\Enums\Scope;

final class ProjectController extends Controller
{
    public function __construct(
        private readonly Access $access,
        private readonly ProjectAccess $projects,
        private readonly CustomFields $customFields,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('projects.view');

        /** @var User $user */
        $user = $request->user();
        $filters = $request->only(['search', 'status', 'visibility', 'mine']);
        $me = app(OrgHierarchy::class)->employeeIdForUser($user->id);
        $status = ProjectStatus::tryFrom((string) ($filters['status'] ?? ''));
        $visibility = ProjectVisibility::tryFrom((string) ($filters['visibility'] ?? ''));

        $paginator = Project::query()
            ->with('manager:id,first_name,last_name')
            ->withCount('members')
            ->visibleTo($user)
            ->search($filters['search'] ?? null)
            ->when($status, fn ($q, $s) => $q->where('projects.status', $s->value),
                fn ($q) => $q->where('projects.status', '!=', ProjectStatus::Archived->value))
            ->when($visibility, fn ($q, $v) => $q->where('projects.visibility', $v->value))
            ->when($request->boolean('mine'), fn ($q) => $me === null ? $q->whereRaw('1 = 0') : $q->involving($me))
            ->orderBy('projects.name')
            ->paginate(12)
            ->withQueryString();

        $canOpen = $this->projects->opener($user);

        return Inertia::render('projects/index', [
            'projects' => $paginator->through(fn (Project $p) => ProjectPresenter::card($p, $canOpen($p))),
            'filters' => $filters,
            'options' => [
                'statuses' => ProjectStatus::options(),
                'visibilities' => ProjectVisibility::options(),
            ],
            'can' => [
                'create' => Gate::allows('projects.create'),
                'fields' => Gate::allows('projects.fields.manage'),
            ],
        ]);
    }

    public function create(Request $request, CreateProject $create): Response
    {
        Gate::authorize('projects.create');

        return Inertia::render('projects/create', [
            'options' => $this->formOptions(),
            'fields' => $this->customFields->present($this->customFields->definitions(), new Project),
            'nextCode' => $create->nextCode(),
        ]);
    }

    public function store(ProjectRequest $request, CreateProject $create): RedirectResponse
    {
        Gate::authorize('projects.create');

        /** @var User $user */
        $user = $request->user();
        $data = $request->attributesForProject();

        // Creators who can only manage "own" projects must be able to reach what they create.
        if (empty($data['manager_employee_id']) && $this->access->scope($user, 'projects.manage') !== Scope::All) {
            $data['manager_employee_id'] = app(OrgHierarchy::class)->employeeIdForUser($user->id);
        }

        $project = $create->handle($data, $request->fieldValues(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project created.')]);

        return Gate::allows('view', $project) ? to_route('projects.show', $project) : to_route('projects.index');
    }

    public function show(Request $request, Project $project): Response
    {
        Gate::authorize('projects.view');
        Gate::authorize('view', $project);

        /** @var User $user */
        $user = $request->user();
        $canManage = $this->projects->canManage($user, $project);
        $canMembers = $this->projects->canManageMembers($user, $project);

        $project->load('manager:id,first_name,last_name');
        $members = $project->members()->with(['employee.position:id,title'])->get()
            ->sortBy([fn (ProjectMember $a, ProjectMember $b) => ($b->role === MemberRole::Lead) <=> ($a->role === MemberRole::Lead), fn (ProjectMember $a, ProjectMember $b) => strcmp($a->employee->full_name, $b->employee->full_name)])
            ->values();

        $totals = $canMembers ? app(AllocationChecker::class)->totals(array_values($members->map(fn (ProjectMember $m) => $m->employee_id)->all())) : [];

        return Inertia::render('projects/show', [
            'project' => ProjectPresenter::full($project, $canManage),
            'members' => $members->map(fn (ProjectMember $m) => ProjectPresenter::member(
                $m,
                $m->employee_id === $project->manager_employee_id,
                $canMembers && $this->projects->canManageMembers($user, $project, $m->employee_id),
                $canMembers ? ($totals[$m->employee_id] ?? 0) : null,
            )),
            'fields' => $this->customFields->present($this->customFields->definitions(), $project),
            'activity' => app(AuditTrail::class)->forSubject($project, 50),
            'options' => [
                'roles' => MemberRole::options(),
                'employees' => $canMembers ? $this->addableEmployees($user, $project) : [],
            ],
            'can' => [
                'update' => $canManage,
                'archive' => $canManage && $project->status !== ProjectStatus::Archived,
                'members' => $canMembers,
            ],
            'allocationLimit' => AllocationChecker::LIMIT,
        ]);
    }

    public function edit(Project $project): Response
    {
        Gate::authorize('projects.view');
        Gate::authorize('update', $project);

        $project->load('manager:id,first_name,last_name');

        return Inertia::render('projects/edit', [
            'project' => ProjectPresenter::full($project, true),
            'options' => $this->formOptions(),
            'fields' => $this->customFields->present($this->customFields->definitions(), $project),
        ]);
    }

    public function update(ProjectRequest $request, Project $project, UpdateProject $update): RedirectResponse
    {
        Gate::authorize('update', $project);

        $update->handle($project, $request->attributesForProject(), $request->fieldValues(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project updated.')]);

        return Gate::allows('view', $project) ? to_route('projects.show', $project) : to_route('projects.index');
    }

    public function archive(Request $request, Project $project, ArchiveProject $archive): RedirectResponse
    {
        Gate::authorize('archive', $project);

        $archive->handle($project, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project archived.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'statuses' => ProjectStatus::options(),
            'visibilities' => ProjectVisibility::options(),
            'priorities' => ProjectPriority::options(),
            'managers' => $this->employeeOptions(),
        ];
    }

    /**
     * @param  list<int>|null  $only
     * @return list<array{value: int, label: string}>
     */
    private function employeeOptions(?array $only = null): array
    {
        return array_values(Employee::query()
            ->employed()
            ->when($only !== null, fn ($q) => $q->whereIn('id', $only ?? []))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn (Employee $e) => ['value' => $e->id, 'label' => $e->full_name])
            ->all());
    }

    /**
     * Employed employees not yet on the project, limited to the user's member-management scope.
     *
     * @return list<array{value: int, label: string}>
     */
    private function addableEmployees(User $user, Project $project): array
    {
        $taken = $project->members()->pluck('employee_id')->all();
        $visible = $this->access->visibleEmployeeIds($user, 'projects.members.manage');
        $allowed = $this->access->scope($user, 'projects.members.manage') === Scope::Own ? null : $visible;

        return array_values(array_filter(
            $this->employeeOptions($allowed),
            fn (array $o) => ! in_array($o['value'], $taken, true),
        ));
    }
}
