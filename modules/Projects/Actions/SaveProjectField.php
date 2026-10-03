<?php

namespace Modules\Projects\Actions;

use Modules\Identity\Models\User;
use Modules\Projects\Enums\FieldType;
use Modules\Projects\Events\ProjectFieldChanged;
use Modules\Projects\Models\ProjectFieldDefinition;

/**
 * Create or update a custom field definition. Key and type are fixed once created.
 */
final class SaveProjectField
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(ProjectFieldDefinition $definition, array $data, ?User $by = null): ProjectFieldDefinition
    {
        $creating = ! $definition->exists;

        if (! $creating) {
            unset($data['key'], $data['type']);
        }

        $definition->fill($data);

        if ($definition->type !== FieldType::Select) {
            $definition->options = null;
        }

        $dirty = array_keys($definition->getDirty());
        $before = [];
        $after = [];

        foreach ($dirty as $key) {
            $before[$key] = $definition->getRawOriginal($key);
            $after[$key] = $definition->getAttributes()[$key] ?? null;
        }

        $definition->save();

        if ($creating || $after !== []) {
            ProjectFieldChanged::dispatch($definition, $creating ? 'created' : 'updated', $creating ? [] : $before, $after, $by?->id);
        }

        return $definition;
    }
}
