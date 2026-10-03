<?php

namespace Modules\Projects\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Modules\Projects\Enums\FieldType;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectFieldDefinition;
use Modules\Projects\Models\ProjectFieldValue;

/**
 * Custom project metadata: validation rules per definition, persistence and presentation.
 */
final class CustomFields
{
    /**
     * @return Collection<int, ProjectFieldDefinition>
     */
    public function definitions(): Collection
    {
        return ProjectFieldDefinition::query()->orderBy('sort')->orderBy('id')->get();
    }

    /**
     * Validation rules for the `fields` input, keyed `fields.<key>`.
     *
     * @param  iterable<ProjectFieldDefinition>  $definitions
     * @return array<string, list<mixed>>
     */
    public function rules(iterable $definitions): array
    {
        $rules = ['fields' => ['nullable', 'array']];

        foreach ($definitions as $definition) {
            $rules["fields.{$definition->key}"] = [
                $definition->is_required ? 'required' : 'nullable',
                ...$this->typeRules($definition),
            ];
        }

        return $rules;
    }

    /**
     * @return list<mixed>
     */
    private function typeRules(ProjectFieldDefinition $definition): array
    {
        return match ($definition->type) {
            FieldType::Text => ['string', 'max:2000'],
            FieldType::Number => ['numeric'],
            FieldType::Date => ['date'],
            FieldType::Url => ['url', 'max:2000'],
            FieldType::Select => [Rule::in($definition->options ?? [])],
        };
    }

    /**
     * Current values keyed by definition key.
     *
     * @return array<string, string|null>
     */
    public function valuesFor(Project $project): array
    {
        $values = [];

        $project->fieldValues()->with('definition:id,key')->get()->each(function (ProjectFieldValue $v) use (&$values): void {
            $values[$v->definition->key] = $v->value;
        });

        return $values;
    }

    /**
     * Persist submitted values (only keys present in the input are touched).
     * Returns before/after maps of what actually changed, keyed `field:<key>`.
     *
     * @param  array<string, mixed>  $input
     * @return array{before: array<string, string|null>, after: array<string, string|null>}
     */
    public function sync(Project $project, array $input): array
    {
        $current = $this->valuesFor($project);
        $before = [];
        $after = [];

        foreach ($this->definitions() as $definition) {
            if (! array_key_exists($definition->key, $input)) {
                continue;
            }

            $raw = $input[$definition->key];
            $new = is_scalar($raw) && trim((string) $raw) !== '' ? trim((string) $raw) : null;
            $old = $current[$definition->key] ?? null;

            if ($new === $old) {
                continue;
            }

            $before["field:{$definition->key}"] = $old;
            $after["field:{$definition->key}"] = $new;

            if ($new === null) {
                ProjectFieldValue::query()
                    ->where('project_id', $project->id)
                    ->where('field_definition_id', $definition->id)
                    ->delete();

                continue;
            }

            ProjectFieldValue::query()->updateOrCreate(
                ['project_id' => $project->id, 'field_definition_id' => $definition->id],
                ['value' => $new],
            );
        }

        return ['before' => $before, 'after' => $after];
    }

    /**
     * Display shape for the Fields tab and edit form.
     *
     * @param  iterable<ProjectFieldDefinition>  $definitions
     * @return list<array{id: int, key: string, label: string, type: string, options: list<string>, required: bool, value: string|null}>
     */
    public function present(iterable $definitions, Project $project): array
    {
        $values = $this->valuesFor($project);
        $rows = [];

        foreach ($definitions as $d) {
            $rows[] = [
                'id' => $d->id,
                'key' => $d->key,
                'label' => $d->label,
                'type' => $d->type->value,
                'options' => $d->options ?? [],
                'required' => $d->is_required,
                'value' => $values[$d->key] ?? null,
            ];
        }

        return $rows;
    }
}
