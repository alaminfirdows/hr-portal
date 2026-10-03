<?php

namespace Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\AccessControl\Actions\AssignRoles;
use Modules\AccessControl\Models\Role;
use Modules\Identity\Actions\InviteUser;
use Modules\Identity\Actions\SetUserActive;
use Modules\Identity\Models\User;
use Modules\Shared\Exceptions\DomainException;

final class UserController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('users.manage');

        $search = (string) $request->string('search');
        $status = (string) $request->string('status');

        $users = User::query()
            ->with(['roles:id,name,slug', 'invitations' => fn ($q) => $q->whereNull('accepted_at')->latest()])
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'roles' => $user->roles->map(fn (Role $r) => ['id' => $r->id, 'name' => $r->name, 'slug' => $r->slug])->all(),
                'pending_invitation' => $user->invitations->first()?->expires_at->toIso8601String(),
            ]);

        return Inertia::render('identity/users/index', [
            'users' => $users,
            'roles' => Role::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'filters' => ['search' => $search, 'status' => $status],
        ]);
    }

    public function store(Request $request, InviteUser $invite): RedirectResponse
    {
        Gate::authorize('users.manage');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('roles', 'slug')],
        ]);

        /** @var User $by */
        $by = $request->user();
        $invite->handle($data['name'], $data['email'], $by, $data['roles'] ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent.')]);

        return back();
    }

    public function resend(Request $request, User $user, InviteUser $invite): RedirectResponse
    {
        Gate::authorize('users.manage');

        if ($user->is_active) {
            throw new DomainException(__('User is already active.'));
        }

        /** @var User $by */
        $by = $request->user();
        $invite->handle($user->name, $user->email, $by);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation resent.')]);

        return back();
    }

    public function roles(Request $request, User $user, AssignRoles $assign): RedirectResponse
    {
        Gate::authorize('roles.manage');

        $data = $request->validate([
            'roles' => ['array'],
            'roles.*' => ['integer', Rule::exists('roles', 'id')],
        ]);

        /** @var User $by */
        $by = $request->user();
        $assign->handle($user, array_values(array_map('intval', $data['roles'] ?? [])), $by);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Roles updated.')]);

        return back();
    }

    public function activate(Request $request, User $user, SetUserActive $action): RedirectResponse
    {
        Gate::authorize('users.manage');

        /** @var User $by */
        $by = $request->user();
        $action->handle($user, true, $by);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User activated.')]);

        return back();
    }

    public function deactivate(Request $request, User $user, SetUserActive $action): RedirectResponse
    {
        Gate::authorize('users.manage');

        /** @var User $by */
        $by = $request->user();
        $action->handle($user, false, $by);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deactivated.')]);

        return back();
    }
}
