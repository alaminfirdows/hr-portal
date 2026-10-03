<?php

namespace Modules\Shared\Null;

use Modules\Shared\Contracts\NotificationPreferences;

final class NullNotificationPreferences implements NotificationPreferences
{
    public function channels(object $notifiable, string $type, array $defaults): array
    {
        return $defaults;
    }
}
