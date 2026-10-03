<?php

namespace Modules\Payroll\Widgets;

use Modules\AccessControl\Services\Access;
use Modules\Identity\Models\User;
use Modules\Payroll\Models\PayrollRun;
use Modules\Shared\Contracts\DashboardWidget;
use Modules\Shared\Enums\Scope;

final class PayrollStatusWidget implements DashboardWidget
{
    public function __construct(private readonly Access $access) {}

    public function key(): string
    {
        return 'payroll.status';
    }

    public function title(): string
    {
        return __('Payroll status');
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
        return 41;
    }

    public function visible(User $user): bool
    {
        return $this->access->scope($user, 'payroll.manage') === Scope::All;
    }

    public function data(User $user): array
    {
        $run = PayrollRun::query()->where('period', now()->format('Y-m'))->first();

        if ($run === null) {
            return [
                'value' => __('Not started'),
                'caption' => __('No run for :period', ['period' => now()->format('F Y')]),
                'href' => route('payroll.runs.index'),
            ];
        }

        return [
            'value' => $run->status->label(),
            'caption' => $run->label(),
            'href' => route('payroll.runs.show', $run),
        ];
    }
}
