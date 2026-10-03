<?php

namespace Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Attendance\Enums\AttendanceStatus;

final class UpsertAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('attendance.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i'],
            'status' => ['nullable', Rule::enum(AttendanceStatus::class)],
            'note' => ['required', 'string', 'max:255'],
        ];
    }
}
