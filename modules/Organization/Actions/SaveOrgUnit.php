<?php

namespace Modules\Organization\Actions;

use Illuminate\Database\Eloquent\Model;
use Modules\Organization\Events\OrganizationChanged;

/**
 * Creates or updates any org unit model and records the change.
 */
final class SaveOrgUnit
{
    /**
     * @template T of Model
     *
     * @param  T  $model
     * @param  array<string, mixed>  $attributes
     * @return T
     */
    public function handle(Model $model, array $attributes, string $labelAttribute, ?int $actorId = null): Model
    {
        $creating = ! $model->exists;
        $before = $creating ? [] : array_intersect_key($model->getAttributes(), $attributes);

        $model->fill($attributes)->save();

        $after = $creating ? $attributes : array_intersect_key($model->getAttributes(), $model->getChanges());
        $before = array_intersect_key($before, $after);

        if ($creating || $after !== []) {
            OrganizationChanged::dispatch($model, $creating ? 'created' : 'updated', (string) $model->getAttribute($labelAttribute), $before, $after, $actorId);
        }

        return $model;
    }
}
