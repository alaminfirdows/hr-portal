<?php

namespace Modules\Identity\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\AccessControl\Models\Role;
use Modules\Identity\Events\UserInvited;
use Modules\Identity\Models\Invitation;
use Modules\Identity\Models\User;
use Modules\Identity\Notifications\InvitationNotification;
use Modules\Shared\Exceptions\DomainException;

/**
 * Creates an inactive account and mails a one-time acceptance link.
 */
final class InviteUser
{
    private const DEFAULT_ROLE = 'employee';

    /**
     * @param  list<string>  $roles  role slugs
     */
    public function handle(string $name, string $email, ?User $by = null, array $roles = []): Invitation
    {
        $email = Str::lower(trim($email));

        if (User::query()->where('email', $email)->where('is_active', true)->exists()) {
            throw new DomainException(__('An active user with this email already exists.'), 'email');
        }

        $roles = $this->permittedRoles($roles, $by);

        $plain = Str::random(48);

        $invitation = DB::transaction(function () use ($name, $email, $by, $roles, $plain): Invitation {
            $user = User::query()->firstOrNew(['email' => $email]);
            $isNew = ! $user->exists;
            $user->forceFill([
                'name' => $name,
                'password' => $user->exists ? $user->password : Hash::make(Str::random(40)),
                'is_active' => false,
            ])->save();

            if ($roles !== null) {
                $user->syncRolesBySlug($roles);
            } elseif ($isNew) {
                $user->syncRolesBySlug([self::DEFAULT_ROLE]);
            }

            $user->invitations()->whereNull('accepted_at')->delete();

            return Invitation::query()->create([
                'email' => $email,
                'token' => Invitation::hashToken($plain),
                'user_id' => $user->id,
                'invited_by' => $by?->id,
                'expires_at' => now()->addDays(Invitation::TTL_DAYS),
            ]);
        });

        $invitation->user->notify(new InvitationNotification($plain, $by?->name));

        UserInvited::dispatch($invitation->user, $by);

        return $invitation;
    }

    /**
     * Requested roles are honoured only for system calls or actors holding roles.manage,
     * and super roles only for super actors. Null means "no explicit roles".
     *
     * @param  list<string>  $roles
     * @return list<string>|null
     */
    private function permittedRoles(array $roles, ?User $by): ?array
    {
        if ($roles === []) {
            return null;
        }

        if ($by === null) {
            return $roles;
        }

        if (! $by->can('roles.manage')) {
            return null;
        }

        if (! $by->isSuper() && Role::query()->whereIn('slug', $roles)->where('is_super', true)->exists()) {
            throw new DomainException(__('Only a super administrator can assign the administrator role.'), 'roles');
        }

        return $roles;
    }
}
