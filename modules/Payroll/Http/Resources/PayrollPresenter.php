<?php

namespace Modules\Payroll\Http\Resources;

use Modules\Payroll\Models\PayComponent;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\Payslip;
use Modules\Settings\Services\SettingsRepository;

/**
 * Array shapes for Inertia props. Money stays in minor units; inputs get plain decimal strings.
 */
final class PayrollPresenter
{
    public static function currency(): string
    {
        return app(SettingsRepository::class)->organization()->currency;
    }

    /**
     * Minor units as a decimal string for form inputs ("1500.00").
     */
    public static function decimal(?int $minor): ?string
    {
        return $minor === null ? null : number_format($minor / 100, 2, '.', '');
    }

    /**
     * @return array<string, mixed>
     */
    public static function component(PayComponent $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'code' => $c->code,
            'type' => $c->type->value,
            'type_label' => $c->type->label(),
            'type_color' => $c->type->color(),
            'calculation' => $c->calculation->value,
            'calculation_label' => $c->calculation->label(),
            'value' => self::decimal($c->value),
            'formula' => $c->formula,
            'is_taxable' => $c->is_taxable,
            'applies_to_all' => $c->applies_to_all,
            'is_active' => $c->is_active,
            'sort' => $c->sort,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function run(PayrollRun $run): array
    {
        return [
            'id' => $run->id,
            'period' => $run->period,
            'label' => $run->label(),
            'status' => $run->status->value,
            'status_label' => $run->status->label(),
            'status_color' => $run->status->color(),
            'employee_count' => $run->employee_count,
            'total_gross' => $run->total_gross,
            'total_deductions' => $run->total_deductions,
            'total_net' => $run->total_net,
            'created_by' => $run->creator->name ?? null,
            'created_by_id' => $run->created_by,
            'calculated_at' => $run->calculated_at?->toIso8601String(),
            'processed_at' => $run->processed_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function payslip(Payslip $p): array
    {
        return [
            'id' => $p->id,
            'employee_id' => $p->employee_id,
            'employee' => $p->employee->full_name,
            'employee_code' => $p->employee->employee_code,
            'period' => $p->run->period,
            'period_label' => $p->run->label(),
            'basic' => $p->basic,
            'gross' => $p->gross,
            'total_deductions' => $p->total_deductions,
            'net' => $p->net,
            'net_clamped' => $p->net_clamped,
            'working_days' => $p->working_days,
            'paid_days' => $p->paid_days,
            'unpaid_days' => $p->unpaid_days,
            'overtime_hours' => round($p->overtime_minutes / 60, 2),
            'published_at' => $p->published_at?->toIso8601String(),
        ];
    }
}
