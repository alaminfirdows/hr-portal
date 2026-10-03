<?php

namespace Modules\Shared\Contracts;

/**
 * Per-user channel choices for a notification type. Implemented by the Notifications module.
 */
interface NotificationPreferences
{
    /**
     * @param  list<'in_app'|'email'>  $defaults
     * @return list<'in_app'|'email'>
     */
    public function channels(object $notifiable, string $type, array $defaults): array;
}
