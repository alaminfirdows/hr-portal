<?php

namespace Modules\Projects\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Projects\Enums\FieldType;
use Modules\Projects\Models\ProjectFieldDefinition;

/**
 * @extends Factory<ProjectFieldDefinition>
 */
class ProjectFieldDefinitionFactory extends Factory
{
    protected $model = ProjectFieldDefinition::class;

    public function definition(): array
    {
        $word = fake()->unique()->word();

        return [
            'key' => str_replace('-', '_', $word).fake()->unique()->numerify('##'),
            'label' => ucfirst($word),
            'type' => FieldType::Text,
            'options' => null,
            'is_required' => false,
            'sort' => 0,
        ];
    }

    public function type(FieldType $type): static
    {
        return $this->state(['type' => $type]);
    }

    public function required(): static
    {
        return $this->state(['is_required' => true]);
    }

    /**
     * @param  list<string>  $options
     */
    public function select(array $options): static
    {
        return $this->state(['type' => FieldType::Select, 'options' => $options]);
    }
}
