<?php

namespace Modules\Leave\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Leave\Enums\AccrualMethod;
use Modules\Leave\Enums\ApprovalStepRole;
use Modules\Leave\Models\LeaveType;

final class LeaveTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('leave.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = $this->route('leaveType');

        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', Rule::unique('leave_types', 'code')->ignore($type instanceof LeaveType ? $type->id : null)],
            'color' => ['required', Rule::in(['gray', 'green', 'blue', 'amber', 'red', 'purple'])],
            'is_paid' => ['boolean'],
            'requires_attachment' => ['boolean'],
            'allow_half_day' => ['boolean'],
            'accrual_method' => ['required', Rule::enum(AccrualMethod::class)],
            'annual_quota' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'carry_forward_cap' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'prorate_new_joiners' => ['boolean'],
            'approval_steps' => ['nullable', 'array'],
            'approval_steps.*' => ['distinct', Rule::enum(ApprovalStepRole::class)],
            'max_consecutive_days' => ['nullable', 'integer', 'min:1', 'max:366'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function attributesForType(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();

        // Keep the canonical order: manager before HR.
        $steps = collect($this->array('approval_steps'))->map(fn ($s) => (string) $s);
        $data['approval_steps'] = collect([ApprovalStepRole::Manager->value, ApprovalStepRole::Hr->value])
            ->filter(fn (string $s) => $steps->contains($s))
            ->values()
            ->all();
        $data['carry_forward_cap'] = $data['carry_forward_cap'] ?? 0;

        return $data;
    }
}
