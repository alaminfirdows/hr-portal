<?php

namespace Modules\Documents\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $document_id
 * @property int $user_id
 * @property int|null $shared_by
 */
#[Fillable(['document_id', 'user_id', 'shared_by'])]
class DocumentShare extends Model {}
