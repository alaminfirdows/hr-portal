<?php

namespace Modules\Projects\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Projects\Enums\FieldType;
use Modules\Projects\Models\ProjectFieldDefinition;

final class ProjectFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('projects.fields.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var ProjectFieldDefinition|null $definition */
        $definition = $this->route('definition');
        $select = ($definition?->type->value ?? $this->input('type')) === FieldType::Select->value;

        return [
            'key' => [$definition ? 'prohibited' : 'required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('project_field_definitions', 'key')],
            'label' => ['required', 'string', 'max:100'],
            'type' => [$definition ? 'prohibited' : 'required', Rule::enum(FieldType::class)],
            'options' => [$select ? 'required' : 'nullable', 'array', 'max:50'],
            'options.*' => ['string', 'max:100', 'distinct'],
            'is_required' => ['sometimes', 'boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function attributesForField(): array
    {
        $data = $this->validated();
        $data['is_required'] = (bool) ($data['is_required'] ?? false);
        $data['sort'] = (int) ($data['sort'] ?? 0);

        if (isset($data['options'])) {
            $data['options'] = array_values($data['options']);
        }

        return $data;
    }
}
