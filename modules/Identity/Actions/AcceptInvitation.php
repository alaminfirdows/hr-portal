<?php

namespace Modules\Identity\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Events\InvitationAccepted;
use Modules\Identity\Models\Invitation;
use Modules\Identity\Models\User;
use Modules\Shared\Exceptions\DomainException;

final class AcceptInvitation
{
    public function handle(string $token, string $password): User
    {
        $found = $this->find($token);

        $user = DB::transaction(function () use ($found, $password): User {
            $invitation = Invitation::query()->with('user')->lockForUpdate()->find($found->id);

            if ($invitation === null || ! $invitation->isUsable()) {
                throw new DomainException(__('This invitation link is invalid or has expired.'), 'token');
            }

            $invitation->forceFill(['accepted_at' => now()])->save();

            $user = $invitation->user;
            $user->forceFill([
                'password' => $password,
                'is_active' => true,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            return $user;
        });

        InvitationAccepted::dispatch($user);

        return $user;
    }

    public function find(string $token): Invitation
    {
        $invitation = Invitation::query()->with('user')->where('token', Invitation::hashToken($token))->first();

        if ($invitation === null || ! $invitation->isUsable()) {
            throw new DomainException(__('This invitation link is invalid or has expired.'), 'token');
        }

        return $invitation;
    }
}
