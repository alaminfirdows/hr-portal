<?php

namespace Modules\Projects\Actions;

use Modules\Identity\Models\User;
use Modules\Projects\Events\ProjectFieldChanged;
use Modules\Projects\Models\ProjectFieldDefinition;

/**
 * Deleting a definition also deletes its stored values (cascade).
 */
final class DeleteProjectField
{
    public function handle(ProjectFieldDefinition $definition, ?User $by = null): void
    {
        $before = ['key' => $definition->key, 'type' => $definition->type->value];
        $definition->delete();

        ProjectFieldChanged::dispatch($definition, 'deleted', $before, [], $by?->id);
    }
}
