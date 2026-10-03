<?php

namespace Modules\Leave\Http\Resources;

use Modules\Leave\Models\LeaveApproval;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Models\LeaveType;

/**
 * Array shapes sent to pages.
 */
final class LeavePresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function request(LeaveRequest $r): array
    {
        return [
            'id' => $r->id,
            'employee' => ['id' => $r->employee->id, 'full_name' => $r->employee->full_name],
            'department' => $r->employee->department?->name,
            'type' => ['id' => $r->type->id, 'name' => $r->type->name, 'color' => $r->type->color],
            'start_date' => $r->start_date->toDateString(),
            'end_date' => $r->end_date->toDateString(),
            'day_part' => $r->day_part->value,
            'day_part_label' => $r->day_part->label(),
            'days' => $r->days,
            'reason' => $r->reason,
            'has_attachment' => $r->attachment_path !== null,
            'status' => $r->status->value,
            'status_label' => $r->status->label(),
            'status_color' => $r->status->color(),
            'current_role' => $r->currentRole()?->label(),
            'created_at' => $r->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function approval(LeaveApproval $a): array
    {
        return [
            'id' => $a->id,
            'step_role' => $a->step_role->label(),
            'approver' => $a->approver?->name,
            'decision' => $a->decision->value,
            'decision_label' => $a->decision->label(),
            'decision_color' => $a->decision->color(),
            'comment' => $a->comment,
            'created_at' => $a->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function type(LeaveType $t): array
    {
        return [
            'id' => $t->id,
            'name' => $t->name,
            'code' => $t->code,
            'color' => $t->color,
            'is_paid' => $t->is_paid,
            'requires_attachment' => $t->requires_attachment,
            'allow_half_day' => $t->allow_half_day,
            'accrual_method' => $t->accrual_method->value,
            'annual_quota' => $t->annual_quota,
            'carry_forward_cap' => $t->carry_forward_cap,
            'prorate_new_joiners' => $t->prorate_new_joiners,
            'approval_steps' => $t->approval_steps,
            'max_consecutive_days' => $t->max_consecutive_days,
            'is_active' => $t->is_active,
        ];
    }
}
