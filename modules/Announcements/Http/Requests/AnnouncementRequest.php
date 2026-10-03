<?php

namespace Modules\Announcements\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Announcements\Enums\Audience;
use Modules\Announcements\Enums\Priority;

final class AnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('announcements.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $audience = $this->input('audience');
        $table = match ($audience) {
            Audience::Department->value => 'departments',
            Audience::Team->value => 'teams',
            default => 'employees',
        };

        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'audience' => ['required', Rule::enum(Audience::class)],
            'target_ids' => [Rule::requiredIf($audience !== null && $audience !== Audience::All->value), 'nullable', 'array'],
            'target_ids.*' => ['integer', Rule::exists($table, 'id')],
            'publish_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:publish_at'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();

        if (empty($data['publish_at'])) {
            unset($data['publish_at']);
        }

        return $data;
    }
}
