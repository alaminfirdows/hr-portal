<?php

namespace Modules\Projects\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $project_id
 * @property int $field_definition_id
 * @property string|null $value
 * @property-read ProjectFieldDefinition $definition
 */
#[Fillable(['project_id', 'field_definition_id', 'value'])]
class ProjectFieldValue extends Model
{
    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<ProjectFieldDefinition, $this>
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(ProjectFieldDefinition::class, 'field_definition_id');
    }
}
