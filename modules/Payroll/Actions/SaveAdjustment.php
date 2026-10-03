<?php

namespace Modules\Payroll\Actions;

use Modules\Payroll\Events\PayrollConfigChanged;
use Modules\Payroll\Models\PayrollAdjustment;
use Modules\Payroll\Models\PayrollRun;
use Modules\Shared\Exceptions\DomainException;

final class SaveAdjustment
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes, ?int $actorId = null): PayrollAdjustment
    {
        $this->assertOpen((string) $attributes['period']);

        $adjustment = PayrollAdjustment::query()->create($attributes);

        PayrollConfigChanged::dispatch($adjustment, 'created', "{$adjustment->name} ({$adjustment->period})", [], $attributes, $actorId);

        return $adjustment;
    }

    public function delete(PayrollAdjustment $adjustment, ?int $actorId = null): void
    {
        $this->assertOpen($adjustment->period);

        app(DeletePayrollConfig::class)->handle($adjustment, "{$adjustment->name} ({$adjustment->period})", $actorId);
    }

    private function assertOpen(string $period): void
    {
        $run = PayrollRun::query()->where('period', $period)->first();

        if ($run !== null && $run->status->isLocked()) {
            throw new DomainException(__('Payroll for :period is already approved; adjustments are locked.', ['period' => $period]), 'period');
        }
    }
}
