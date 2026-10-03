<?php

namespace Modules\Reports\Reports\Projects;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\AccessControl\Services\Access;
use Modules\Identity\Models\User;
use Modules\Reports\Reports\AbstractReport;

final class ActiveProjectsReport extends AbstractReport
{
    public function key(): string
    {
        return 'projects.active';
    }

    public function title(): string
    {
        return __('Active projects');
    }

    public function description(): string
    {
        return __('Active projects with member counts. Counts only members you may see.');
    }

    public function group(): string
    {
        return 'Projects';
    }

    public function filters(): array
    {
        return [];
    }

    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => __('Code'), 'type' => 'text'],
            ['key' => 'name', 'label' => __('Project'), 'type' => 'text'],
            ['key' => 'manager', 'label' => __('Manager'), 'type' => 'text'],
            ['key' => 'priority', 'label' => __('Priority'), 'type' => 'text'],
            ['key' => 'start_date', 'label' => __('Start'), 'type' => 'text'],
            ['key' => 'end_date', 'label' => __('End'), 'type' => 'text'],
            ['key' => 'members', 'label' => __('Members'), 'type' => 'number'],
        ];
    }

    public function chart(): array
    {
        return ['label' => 'name', 'value' => 'members'];
    }

    public function query(array $filters, User $user): array
    {
        $ids = app(Access::class)->visibleEmployeeIds($user, 'reports.view');

        $members = DB::table('project_members')
            ->join('employees as me', fn ($j) => $j->on('me.id', '=', 'project_members.employee_id')->whereNull('me.deleted_at'))
            ->when($ids !== null, fn (Builder $q) => $q->whereIn('project_members.employee_id', $ids))
            ->selectRaw('project_members.project_id, count(*) as member_count')
            ->groupBy('project_members.project_id');

        return DB::table('projects')
            ->leftJoinSub($members, 'm', 'm.project_id', '=', 'projects.id')
            ->leftJoin('employees as mgr', 'mgr.id', '=', 'projects.manager_employee_id')
            ->where('projects.status', 'active')
            ->when($ids !== null, fn (Builder $q) => $q->whereNotNull('m.member_count'))
            ->orderBy('projects.name')
            ->get(['projects.code', 'projects.name', 'projects.priority', 'projects.start_date', 'projects.end_date', 'm.member_count', 'mgr.first_name', 'mgr.last_name'])
            ->map(fn (object $r) => [
                'code' => (string) $r->code,
                'name' => (string) $r->name,
                'manager' => $r->first_name === null ? '' : $this->fullName($r->first_name, $r->last_name),
                'priority' => ucfirst((string) $r->priority),
                'start_date' => $r->start_date === null ? '' : substr((string) $r->start_date, 0, 10),
                'end_date' => $r->end_date === null ? '' : substr((string) $r->end_date, 0, 10),
                'members' => (int) $r->member_count,
            ])
            ->all();
    }
}
