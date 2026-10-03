<?php

namespace Modules\Announcements\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $announcement_id
 * @property int $target_id
 */
#[Fillable(['announcement_id', 'target_id'])]
class AnnouncementTarget extends Model
{
    public $timestamps = false;
}
