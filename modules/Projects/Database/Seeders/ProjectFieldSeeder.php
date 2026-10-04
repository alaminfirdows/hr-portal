<?php

namespace Modules\Projects\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Projects\Actions\SaveProjectField;
use Modules\Projects\Enums\FieldType;
use Modules\Projects\Models\ProjectFieldDefinition;

/**
 * Default custom project fields. Idempotent by key.
 */
class ProjectFieldSeeder extends Seeder
{
    public function run(SaveProjectField $saveField): void
    {
        $fields = [
            ['key' => 'client_name', 'label' => 'Client', 'type' => FieldType::Text->value, 'is_required' => false, 'sort' => 10],
            ['key' => 'project_type', 'label' => 'Project Type', 'type' => FieldType::Select->value, 'options' => ['Internal', 'Client', 'R&D'], 'is_required' => false, 'sort' => 20],
            ['key' => 'technology', 'label' => 'Technology', 'type' => FieldType::Text->value, 'is_required' => false, 'sort' => 30],
        ];

        foreach ($fields as $field) {
            if (! ProjectFieldDefinition::query()->where('key', $field['key'])->exists()) {
                $saveField->handle(new ProjectFieldDefinition, $field);
            }
        }
    }
}
