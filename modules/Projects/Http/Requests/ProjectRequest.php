<?php

namespace Modules\Projects\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Projects\Enums\ProjectPriority;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Enums\ProjectVisibility;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\CustomFields;
use Modules\Shared\ValueObjects\Money;

final class ProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project ? Gate::allows('update', $project) : Gate::allows('projects.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Project|null $project */
        $project = $this->route('project');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:30', Rule::unique('projects', 'code')->ignore($project)],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'visibility' => ['required', Rule::enum(ProjectVisibility::class)],
            'priority' => ['required', Rule::enum(ProjectPriority::class)],
            'manager_employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'client' => ['nullable', 'string', 'max:255'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            ...app(CustomFields::class)->rules(app(CustomFields::class)->definitions()),
        ];
    }

    /**
     * Project columns only (budget converted to minor units).
     *
     * @return array<string, mixed>
     */
    public function attributesForProject(): array
    {
        $data = collect($this->validated())->except(['fields'])->all();

        $data['budget'] = isset($data['budget']) && $data['budget'] !== ''
            ? Money::fromDecimal($data['budget'])->minor
            : null;

        if (($data['status'] ?? null) === null) {
            unset($data['status']);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function fieldValues(): array
    {
        /** @var array<string, mixed> $fields */
        $fields = $this->validated('fields') ?? [];

        return $fields;
    }
}
