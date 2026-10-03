<?php

namespace Modules\Notifications\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\Notifications\Models\NotificationPreference;
use Modules\Shared\Contracts\NotificationPreferences;

/**
 * A stored row overrides the notification's default channels. In-app can only be muted
 * for types whose default includes it.
 */
final class DatabaseNotificationPreferences implements NotificationPreferences
{
    public function channels(object $notifiable, string $type, array $defaults): array
    {
        if (! $notifiable instanceof Model) {
            return $defaults;
        }

        $preference = NotificationPreference::query()
            ->where('user_id', $notifiable->getKey())
            ->where('type', $type)
            ->first();

        if ($preference === null) {
            return $defaults;
        }

        $channels = [];
        if ($preference->in_app) {
            $channels[] = 'in_app';
        }
        if ($preference->email) {
            $channels[] = 'email';
        }

        return $channels;
    }
}
