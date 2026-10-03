<?php

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Payroll\Enums\CalculationMethod;
use Modules\Payroll\Enums\ComponentType;
use Modules\Payroll\Models\PayComponent;

/**
 * @extends Factory<PayComponent>
 */
class PayComponentFactory extends Factory
{
    protected $model = PayComponent::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->bothify('Component ####'),
            'code' => strtoupper(fake()->unique()->lexify('????')),
            'type' => ComponentType::Earning,
            'calculation' => CalculationMethod::Fixed,
            'value' => 10000,
            'formula' => null,
            'is_taxable' => false,
            'applies_to_all' => true,
            'is_active' => true,
            'sort' => 100,
        ];
    }

    public function percent(int $basisPoints): static
    {
        return $this->state(['calculation' => CalculationMethod::PercentOfBasic, 'value' => $basisPoints]);
    }

    public function formula(string $formula): static
    {
        return $this->state(['calculation' => CalculationMethod::Formula, 'value' => null, 'formula' => $formula]);
    }

    public function deduction(): static
    {
        return $this->state(['type' => ComponentType::Deduction]);
    }
}
