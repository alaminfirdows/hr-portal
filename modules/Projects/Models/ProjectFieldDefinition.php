<?php

namespace Modules\Projects\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Projects\Database\Factories\ProjectFieldDefinitionFactory;
use Modules\Projects\Enums\FieldType;

/**
 * @property int $id
 * @property string $key
 * @property string $label
 * @property FieldType $type
 * @property list<string>|null $options Allowed values for select fields
 * @property bool $is_required
 * @property int $sort
 */
#[Fillable(['key', 'label', 'type', 'options', 'is_required', 'sort'])]
class ProjectFieldDefinition extends Model
{
    /** @use HasFactory<ProjectFieldDefinitionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => FieldType::class,
            'options' => 'array',
            'is_required' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * @return HasMany<ProjectFieldValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(ProjectFieldValue::class, 'field_definition_id');
    }
}
