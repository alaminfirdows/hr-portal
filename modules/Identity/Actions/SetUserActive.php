<?php

namespace Modules\Identity\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Events\UserActivationChanged;
use Modules\Identity\Models\User;
use Modules\Shared\Exceptions\DomainException;

final class SetUserActive
{
    public function handle(User $user, bool $active, ?User $by = null): User
    {
        if ($by !== null && $by->is($user) && ! $active) {
            throw new DomainException(__('You cannot deactivate your own account.'));
        }

        if ($user->is_active === $active) {
            return $user;
        }

        $user->forceFill(['is_active' => $active])->save();

        if (! $active) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        UserActivationChanged::dispatch($user, $by);

        return $user;
    }
}
