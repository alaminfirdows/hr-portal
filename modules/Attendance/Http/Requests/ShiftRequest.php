<?php

namespace Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('shifts.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'half_day_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'overtime_after_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
