<?php

namespace Modules\Payroll\Actions;

use Modules\Payroll\Enums\CalculationMethod;
use Modules\Payroll\Events\PayrollConfigChanged;
use Modules\Payroll\Models\PayComponent;
use Modules\Payroll\Services\FormulaEvaluator;

final class SavePayComponent
{
    public function __construct(private readonly FormulaEvaluator $formulas) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(PayComponent $component, array $attributes, ?int $actorId = null): PayComponent
    {
        $method = CalculationMethod::from((string) ($attributes['calculation'] ?? $component->calculation->value));

        if ($method === CalculationMethod::Formula) {
            $this->formulas->assertValid((string) ($attributes['formula'] ?? ''));
        }

        $creating = ! $component->exists;
        $before = $creating ? [] : array_intersect_key($component->getAttributes(), $attributes);

        $component->fill($attributes)->save();

        $after = $creating ? $attributes : array_intersect_key($component->getAttributes(), $component->getChanges());
        $before = array_intersect_key($before, $after);

        if ($creating || $after !== []) {
            PayrollConfigChanged::dispatch($component, $creating ? 'created' : 'updated', $component->name, $before, $after, $actorId);
        }

        return $component;
    }
}
