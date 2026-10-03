<?php

namespace Modules\Identity\Listeners;

use Illuminate\Auth\Events\Login;
use Modules\Identity\Models\User;

final class RecordLastLogin
{
    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
        }
    }
}
