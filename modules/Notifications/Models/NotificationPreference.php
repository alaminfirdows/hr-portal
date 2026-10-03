<?php

namespace Modules\Notifications\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property bool $in_app
 * @property bool $email
 */
#[Fillable(['user_id', 'type', 'in_app', 'email'])]
class NotificationPreference extends Model
{
    protected function casts(): array
    {
        return ['in_app' => 'boolean', 'email' => 'boolean'];
    }
}
