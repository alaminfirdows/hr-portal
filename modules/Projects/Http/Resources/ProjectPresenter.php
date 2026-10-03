<?php

namespace Modules\Projects\Http\Resources;

use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectMember;
use Modules\Shared\ValueObjects\Money;

/**
 * Array shapes sent to pages. Restricted projects only expose name, code, status
 * and visibility to people who cannot open them.
 */
final class ProjectPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function card(Project $p, bool $canOpen): array
    {
        $base = [
            'id' => $p->id,
            'name' => $p->name,
            'code' => $p->code,
            'status' => $p->status->value,
            'status_label' => $p->status->label(),
            'status_color' => $p->status->color(),
            'visibility' => $p->visibility->value,
            'visibility_label' => $p->visibility->label(),
            'visibility_color' => $p->visibility->color(),
            'can_open' => $canOpen,
        ];

        if (! $canOpen) {
            return $base;
        }

        return [
            ...$base,
            'description' => $p->description,
            'client' => $p->client,
            'priority' => $p->priority->value,
            'priority_label' => $p->priority->label(),
            'priority_color' => $p->priority->color(),
            'manager' => $p->manager?->full_name,
            'members_count' => $p->members_count ?? 0,
            'start_date' => $p->start_date?->toDateString(),
            'end_date' => $p->end_date?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function full(Project $p, bool $withBudget): array
    {
        return [
            ...self::card($p, true),
            'manager_employee_id' => $p->manager_employee_id,
            'budget' => $withBudget && $p->budget !== null ? Money::of($p->budget)->toDecimal() : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function member(ProjectMember $m, bool $isManager, bool $editable, ?int $allocationTotal): array
    {
        $e = $m->employee;

        return [
            'id' => $m->id,
            'employee_id' => $e->id,
            'full_name' => $e->full_name,
            'photo_url' => $e->photoUrl(),
            'position' => $e->position?->title,
            'role' => $m->role->value,
            'role_label' => $m->role->label(),
            'allocation_percent' => $m->allocation_percent,
            'joined_on' => $m->joined_on->toDateString(),
            'is_manager' => $isManager,
            'editable' => $editable,
            'can_remove' => $editable && ! $isManager,
            'allocation_total' => $allocationTotal,
        ];
    }
}
