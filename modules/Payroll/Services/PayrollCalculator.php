<?php

namespace Modules\Payroll\Services;

use Modules\Payroll\Data\ComponentSpec;
use Modules\Payroll\Data\PayrollContext;
use Modules\Payroll\Data\PayslipLineResult;
use Modules\Payroll\Data\PayslipResult;
use Modules\Payroll\Enums\CalculationMethod;
use Modules\Payroll\Enums\ComponentType;
use Modules\Shared\Exceptions\DomainException;
use Modules\Shared\ValueObjects\Money;

/**
 * Pure payroll engine: context in, payslip out. Order of evaluation:
 * pro-rated basic, components in sort order (tax slab components excepted), unpaid-day deduction,
 * one-off adjustments, then tax slab components over the taxable total. Net is clamped at zero.
 */
final class PayrollCalculator
{
    public function __construct(
        private readonly FormulaEvaluator $formulas,
        private readonly TaxCalculator $tax,
    ) {}

    public function calculate(PayrollContext $context): PayslipResult
    {
        $workingDays = max(0, $context->workingDays);
        $employedDays = max(0, $workingDays > 0 ? min($context->employedWorkingDays, $workingDays) : $context->employedWorkingDays);
        $unpaidDays = min((float) $employedDays, max(0.0, $context->unpaidLeaveDays + $context->absentDays));
        $paidDays = max(0.0, $employedDays - $unpaidDays);

        $basic = $workingDays > 0 && $employedDays < $workingDays
            ? $context->monthlyBasic->multiply($employedDays / $workingDays)
            : $context->monthlyBasic;

        $lines = [new PayslipLineResult('BASIC', 'Basic salary', ComponentType::Earning, $basic)];
        $gross = $basic;
        $taxable = $basic;

        foreach ($context->components as $spec) {
            if ($spec->method === CalculationMethod::TaxSlab) {
                continue;
            }

            $amount = $spec->override !== null
                ? Money::of($spec->override)
                : $this->amountFor($spec, $context, $basic, $gross, $paidDays, $workingDays);

            if ($amount->minor <= 0) {
                continue;
            }

            $lines[] = new PayslipLineResult($spec->code, $spec->name, $spec->type, $amount);

            if ($spec->type === ComponentType::Earning) {
                $gross = $gross->add($amount);
                $taxable = $spec->taxable ? $taxable->add($amount) : $taxable;
            }
        }

        $unpaidDeduction = $workingDays > 0 && $unpaidDays > 0
            ? $context->monthlyBasic->multiply($unpaidDays / $workingDays)
            : Money::zero();

        if ($unpaidDeduction->minor > 0) {
            $lines[] = new PayslipLineResult('UNPAID', 'Unpaid days', ComponentType::Deduction, $unpaidDeduction);
        }

        foreach ($context->adjustments as $adjustment) {
            if ($adjustment->amount->minor <= 0) {
                continue;
            }

            $lines[] = new PayslipLineResult('ADJ', $adjustment->name, $adjustment->type, $adjustment->amount);

            if ($adjustment->type === ComponentType::Earning) {
                $gross = $gross->add($adjustment->amount);
                $taxable = $taxable->add($adjustment->amount);
            }
        }

        $taxBase = Money::of(max(0, $taxable->minor - $unpaidDeduction->minor));

        foreach ($context->components as $spec) {
            if ($spec->method !== CalculationMethod::TaxSlab) {
                continue;
            }

            $amount = $spec->override !== null ? Money::of($spec->override) : $this->tax->calculate($taxBase, $context->taxBrackets);

            if ($amount->minor > 0) {
                $lines[] = new PayslipLineResult($spec->code, $spec->name, $spec->type, $amount);
            }
        }

        $deductions = Money::zero();
        foreach ($lines as $line) {
            if ($line->type === ComponentType::Deduction) {
                $deductions = $deductions->add($line->amount);
            }
        }

        $net = $gross->subtract($deductions);
        $clamped = $net->isNegative();

        return new PayslipResult(
            basic: $basic,
            gross: $gross,
            totalDeductions: $deductions,
            net: $clamped ? Money::zero() : $net,
            netClamped: $clamped,
            workingDays: $workingDays,
            paidDays: $paidDays,
            unpaidDays: $unpaidDays,
            overtimeMinutes: $context->overtimeMinutes,
            lines: $lines,
        );
    }

    private function amountFor(ComponentSpec $spec, PayrollContext $context, Money $basic, Money $gross, float $paidDays, int $workingDays): Money
    {
        return match ($spec->method) {
            CalculationMethod::Fixed => Money::of($spec->value ?? 0),
            CalculationMethod::PercentOfBasic => $basic->multiply(($spec->value ?? 0) / 10000),
            CalculationMethod::Formula => $this->formula($spec, $context, $basic, $gross, $paidDays, $workingDays),
            CalculationMethod::TaxSlab => Money::zero(),
        };
    }

    private function formula(ComponentSpec $spec, PayrollContext $context, Money $basic, Money $gross, float $paidDays, int $workingDays): Money
    {
        try {
            $result = $this->formulas->evaluate($spec->formula ?? '', [
                'basic' => $basic->minor,
                'gross' => $gross->minor,
                'working_days' => $workingDays,
                'paid_days' => $paidDays,
                'unpaid_leave_days' => $context->unpaidLeaveDays,
                'absent_days' => $context->absentDays,
                'overtime_hours' => $context->overtimeMinutes / 60,
                'years_of_service' => $context->yearsOfService,
            ]);
        } catch (DomainException $e) {
            throw new DomainException(__('Component :name (:code): :error', ['name' => $spec->name, 'code' => $spec->code, 'error' => $e->getMessage()]), 'formula');
        }

        return Money::of(max(0, (int) round($result, 0, PHP_ROUND_HALF_UP)));
    }
}
