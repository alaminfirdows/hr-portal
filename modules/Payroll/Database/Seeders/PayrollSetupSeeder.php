<?php

namespace Modules\Payroll\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Payroll\Enums\CalculationMethod;
use Modules\Payroll\Enums\ComponentType;
use Modules\Payroll\Models\PayComponent;
use Modules\Payroll\Models\TaxSlab;

/**
 * Starter pay components and monthly tax slabs. Sample values: HR must review them before the first real run.
 * Amounts are minor units; percentages are basis points.
 */
class PayrollSetupSeeder extends Seeder
{
    public function run(): void
    {
        $this->components();
        $this->slabs();
    }

    private function components(): void
    {
        $rows = [
            ['HRA', 'House rent', ComponentType::Earning, CalculationMethod::PercentOfBasic, 5000, null, true, 10],
            ['MED', 'Medical', ComponentType::Earning, CalculationMethod::Fixed, 25000, null, true, 20],
            ['TRN', 'Transport', ComponentType::Earning, CalculationMethod::Fixed, 15000, null, true, 30],
            ['OT', 'Overtime', ComponentType::Earning, CalculationMethod::Formula, null, 'overtime_hours * basic / 208 * 1.5', true, 40],
            ['PF', 'Provident fund', ComponentType::Deduction, CalculationMethod::PercentOfBasic, 1000, null, false, 100],
            ['TAX', 'Income tax', ComponentType::Deduction, CalculationMethod::TaxSlab, null, null, false, 110],
        ];

        foreach ($rows as [$code, $name, $type, $calculation, $value, $formula, $taxable, $sort]) {
            PayComponent::query()->firstOrCreate(['code' => $code], [
                'name' => $name,
                'type' => $type,
                'calculation' => $calculation,
                'value' => $value,
                'formula' => $formula,
                'is_taxable' => $taxable,
                'applies_to_all' => true,
                'is_active' => true,
                'sort' => $sort,
            ]);
        }
    }

    private function slabs(): void
    {
        if (TaxSlab::query()->exists()) {
            return;
        }

        foreach ([[0, 300000, 0], [300000, 600000, 500], [600000, 1000000, 1000], [1000000, null, 1500]] as [$min, $max, $rate]) {
            TaxSlab::query()->create(['min_amount' => $min, 'max_amount' => $max, 'rate_bp' => $rate]);
        }
    }
}
