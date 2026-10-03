<?php

namespace Modules\Payroll\Widgets;

use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;
use Modules\Payroll\Models\Payslip;
use Modules\Settings\Services\SettingsRepository;
use Modules\Shared\Contracts\DashboardWidget;
use Modules\Shared\ValueObjects\Money;

final class LatestPayslipWidget implements DashboardWidget
{
    public function key(): string
    {
        return 'payroll.latest_payslip';
    }

    public function title(): string
    {
        return __('Latest payslip');
    }

    public function type(): string
    {
        return 'stat';
    }

    public function size(): int
    {
        return 1;
    }

    public function order(): int
    {
        return 40;
    }

    public function visible(User $user): bool
    {
        return $this->latest($user) !== null;
    }

    public function data(User $user): array
    {
        $payslip = $this->latest($user);

        if ($payslip === null) {
            return ['value' => '-', 'caption' => __('No payslip yet'), 'href' => route('payroll.payslips.index')];
        }

        $currency = app(SettingsRepository::class)->organization()->currency;

        return [
            'value' => Money::of($payslip->net)->format($currency),
            'caption' => __('Net pay for :period', ['period' => $payslip->run->label()]),
            'href' => route('payroll.payslips.show', $payslip),
        ];
    }

    private function latest(User $user): ?Payslip
    {
        $employeeId = Employee::query()->where('user_id', $user->id)->value('id');

        if ($employeeId === null) {
            return null;
        }

        return Payslip::query()
            ->where('employee_id', $employeeId)
            ->whereNotNull('published_at')
            ->with('run')
            ->join('payroll_runs', 'payroll_runs.id', '=', 'payslips.payroll_run_id')
            ->orderByDesc('payroll_runs.period')
            ->select('payslips.*')
            ->first();
    }
}
