<?php

namespace Modules\AccessControl\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\AccessControl\Actions\DeleteRole;
use Modules\AccessControl\Actions\SaveRole;
use Modules\AccessControl\Models\Permission;
use Modules\AccessControl\Models\Role;
use Modules\Identity\Models\User;
use Modules\Shared\Enums\Scope;

final class RoleController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('roles.manage');

        return Inertia::render('access/roles/index', [
            'roles' => Role::query()
                ->withCount(['users', 'permissions'])
                ->orderByDesc('is_super')
                ->orderBy('name')
                ->get()
                ->map(fn (Role $r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'slug' => $r->slug,
                    'description' => $r->description,
                    'is_super' => $r->is_super,
                    'is_system' => $r->is_system,
                    'users_count' => $r->users_count,
                    'permissions_count' => $r->permissions_count,
                ]),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('roles.manage');

        return Inertia::render('access/roles/edit', [
            'role' => null,
            ...$this->formProps(),
        ]);
    }

    public function edit(Role $role): Response
    {
        Gate::authorize('roles.manage');

        $role->load('permissions');

        return Inertia::render('access/roles/edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'is_super' => $role->is_super,
                'is_system' => $role->is_system,
                'permissions' => $role->permissions->mapWithKeys(fn (Permission $p) => [$p->key => $p->pivot->scope->value]),
            ],
            ...$this->formProps(),
        ]);
    }

    public function store(Request $request, SaveRole $save): RedirectResponse
    {
        Gate::authorize('roles.manage');

        $data = $this->validated($request);
        /** @var User $by */
        $by = $request->user();
        $role = $save->handle(null, $data['name'], $data['description'] ?? null, $data['permissions'] ?? [], $by);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created.')]);

        return redirect()->route('access.roles.edit', $role);
    }

    public function update(Request $request, Role $role, SaveRole $save): RedirectResponse
    {
        Gate::authorize('roles.manage');

        $data = $this->validated($request);
        /** @var User $by */
        $by = $request->user();
        $save->handle($role, $data['name'], $data['description'] ?? null, $data['permissions'] ?? [], $by);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role saved.')]);

        return back();
    }

    public function destroy(Role $role, DeleteRole $delete): RedirectResponse
    {
        Gate::authorize('roles.manage');

        $delete->handle($role);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return redirect()->route('access.roles.index');
    }

    /**
     * @return array{name: string, description?: string|null, permissions?: array<string, string>}
     */
    private function validated(Request $request): array
    {
        /** @var array{name: string, description?: string|null, permissions?: array<string, string>} */
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::enum(Scope::class)],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(): array
    {
        return [
            'groups' => Permission::query()
                ->orderBy('module')
                ->orderBy('key')
                ->get(['key', 'module', 'description'])
                ->groupBy('module')
                ->map(fn ($items, $module) => [
                    'module' => $module,
                    'permissions' => $items->map(fn (Permission $p) => ['key' => $p->key, 'description' => $p->description])->values(),
                ])
                ->values(),
            'scopes' => Scope::options(),
        ];
    }
}
