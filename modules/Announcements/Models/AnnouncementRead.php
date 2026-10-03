<?php

namespace Modules\Announcements\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $announcement_id
 * @property int $user_id
 * @property CarbonImmutable $read_at
 */
#[Fillable(['announcement_id', 'user_id', 'read_at'])]
class AnnouncementRead extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['read_at' => 'immutable_datetime'];
    }
}
