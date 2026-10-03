<?php

namespace Modules\Employees\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class OwnProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['nullable', 'string', 'max:50'],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'emergency_contact' => ['nullable', 'array'],
            'emergency_contact.name' => ['nullable', 'string', 'max:100'],
            'emergency_contact.relation' => ['nullable', 'string', 'max:50'],
            'emergency_contact.phone' => ['nullable', 'string', 'max:50'],
            'skills' => ['nullable', 'array', 'max:30'],
            'skills.*' => ['string', 'max:50'],
            'links' => ['nullable', 'array', 'max:10'],
            'links.*' => ['nullable', 'url', 'max:255'],
        ];
    }
}
