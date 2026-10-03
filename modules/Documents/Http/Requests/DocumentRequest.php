<?php

namespace Modules\Documents\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Documents\Enums\DocumentVisibility;

/**
 * Metadata rules shared by file upload, link creation and edit.
 */
class DocumentRequest extends FormRequest
{
    public const MAX_KB = 20480;

    public const MIMES = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt', 'png', 'jpg', 'jpeg'];

    public function authorize(): bool
    {
        return Gate::allows('documents.upload');
    }

    protected function prepareForValidation(): void
    {
        $tags = $this->input('tags');

        if (is_string($tags)) {
            $this->merge(['tags' => array_values(array_filter(array_map('trim', explode(',', $tags)), fn (string $t) => $t !== ''))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'folder_id' => ['nullable', 'integer', 'exists:folders,id'],
            'category' => ['nullable', 'string', 'max:100'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:40'],
            'visibility' => ['required', Rule::enum(DocumentVisibility::class)],
            'target_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'target_team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'target_project_id' => ['nullable', 'integer', 'min:1'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'expires_on' => ['nullable', 'date'],
            'user_ids' => [Rule::requiredIf(fn () => $this->input('visibility') === DocumentVisibility::Users->value), 'nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->safe()->except(['file']);

        return $data;
    }
}
