<?php

namespace Modules\Reports\Reports\Payroll;

use Modules\AccessControl\Services\Access;
use Modules\Identity\Models\User;
use Modules\Reports\Reports\AbstractReport;
use Modules\Shared\Enums\Scope;

final class PayrollComponentsReport extends AbstractReport
{
    public function key(): string
    {
        return 'payroll.components';
    }

    public function title(): string
    {
        return __('Payroll summary by component');
    }

    public function description(): string
    {
        return __('Totals per pay component across payroll runs whose period falls in the date range (drafts excluded).');
    }

    public function group(): string
    {
        return 'Payroll';
    }

    public function permission(): string
    {
        return 'payroll.manage';
    }

    /**
     * Rows are limited by the reports.view scope, so payroll data needs payroll.manage across the company.
     */
    public function accessibleBy(User $user): bool
    {
        return parent::accessibleBy($user) && app(Access::class)->scope($user, 'payroll.manage') === Scope::All;
    }

    public function filters(): array
    {
        return [
            ['key' => 'from', 'label' => __('From'), 'type' => 'date'],
            ['key' => 'to', 'label' => __('To'), 'type' => 'date'],
            ['key' => 'department_id', 'label' => __('Department'), 'type' => 'department'],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'component', 'label' => __('Component'), 'type' => 'text'],
            ['key' => 'type', 'label' => __('Type'), 'type' => 'text'],
            ['key' => 'employees', 'label' => __('Employees'), 'type' => 'number'],
            ['key' => 'total', 'label' => __('Total'), 'type' => 'money'],
        ];
    }

    public function chart(): array
    {
        return ['label' => 'component', 'value' => 'total'];
    }

    public function query(array $filters, User $user): array
    {
        return $this->employees($user, $filters)
            ->join('payslips as p', 'p.employee_id', '=', 'employees.id')
            ->join('payroll_runs as r', 'r.id', '=', 'p.payroll_run_id')
            ->join('payslip_lines as l', 'l.payslip_id', '=', 'p.id')
            ->where('r.status', '!=', 'draft')
            ->where('r.period', '>=', substr($filters['from'], 0, 7))
            ->where('r.period', '<=', substr($filters['to'], 0, 7))
            ->selectRaw('l.code, l.name, l.type, count(distinct p.id) as employees, sum(l.amount) as total, min(l.sort) as sort')
            ->groupBy('l.code', 'l.name', 'l.type')
            ->get()
            ->sortBy([['type', 'asc'], ['sort', 'asc'], ['name', 'asc']])
            ->map(fn (object $r) => [
                'component' => (string) $r->name,
                'type' => ucfirst((string) $r->type),
                'employees' => (int) $r->employees,
                'total' => $this->money($r->total),
            ])
            ->values()
            ->all();
    }
}
