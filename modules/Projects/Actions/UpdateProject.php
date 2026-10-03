<?php

namespace Modules\Projects\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\User;
use Modules\Projects\Events\ProjectUpdated;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\CustomFields;

final class UpdateProject
{
    public function __construct(
        private readonly CustomFields $customFields,
        private readonly SyncProjectManager $syncManager,
    ) {}

    /**
     * @param  array<string, mixed>  $data  project columns (budget in minor units)
     * @param  array<string, mixed>|null  $fields  custom field values; null leaves them untouched
     */
    public function handle(Project $project, array $data, ?array $fields = null, ?User $by = null): Project
    {
        if (empty($data['code'])) {
            unset($data['code']);
        }

        $previousManager = $project->manager_employee_id;
        $project->fill($data);
        $dirty = array_keys($project->getDirty());
        $before = [];
        $after = [];

        foreach ($dirty as $key) {
            $before[$key] = $project->getRawOriginal($key);
            $after[$key] = $project->getAttributes()[$key] ?? null;
        }

        DB::transaction(function () use ($project, $previousManager, $fields, &$before, &$after): void {
            $project->save();

            if (array_key_exists('manager_employee_id', $after)) {
                $this->syncManager->handle($project, $previousManager);
            }

            if ($fields !== null) {
                $changed = $this->customFields->sync($project, $fields);
                $before = [...$before, ...$changed['before']];
                $after = [...$after, ...$changed['after']];
            }
        });

        if ($after !== []) {
            ProjectUpdated::dispatch($project, $before, $after, $by?->id);
        }

        return $project;
    }
}
