<?php

namespace Modules\Projects\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\User;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Events\ProjectCreated;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\CustomFields;

final class CreateProject
{
    public function __construct(
        private readonly CustomFields $customFields,
        private readonly SyncProjectManager $syncManager,
    ) {}

    /**
     * @param  array<string, mixed>  $data  project columns (budget in minor units)
     * @param  array<string, mixed>  $fields  custom field values keyed by definition key
     */
    public function handle(array $data, array $fields = [], ?User $by = null): Project
    {
        $data['code'] = empty($data['code']) ? $this->nextCode() : $data['code'];
        $data['status'] ??= ProjectStatus::Planned->value;

        $project = DB::transaction(function () use ($data, $fields): Project {
            $project = Project::query()->create($data);
            $this->syncManager->handle($project);
            $this->customFields->sync($project, $fields);

            return $project;
        });

        ProjectCreated::dispatch($project, $by?->id);

        return $project;
    }

    public function nextCode(): string
    {
        $number = (int) Project::query()->max('id') + 1;

        do {
            $code = sprintf('PRJ-%03d', $number++);
        } while (Project::query()->where('code', $code)->exists());

        return $code;
    }
}
