<?php

use Modules\Payroll\Data\AdjustmentSpec;
use Modules\Payroll\Data\ComponentSpec;
use Modules\Payroll\Data\PayrollContext;
use Modules\Payroll\Data\PayslipResult;
use Modules\Payroll\Data\TaxBracket;
use Modules\Payroll\Enums\CalculationMethod as Method;
use Modules\Payroll\Enums\ComponentType as Type;
use Modules\Payroll\Services\FormulaEvaluator;
use Modules\Payroll\Services\PayrollCalculator;
use Modules\Payroll\Services\TaxCalculator;
use Modules\Shared\Exceptions\DomainException;
use Modules\Shared\ValueObjects\Money;

function calc(PayrollContext $context): PayslipResult
{
    return (new PayrollCalculator(new FormulaEvaluator, new TaxCalculator))->calculate($context);
}

/**
 * @param  list<ComponentSpec>  $components
 * @param  list<AdjustmentSpec>  $adjustments
 * @param  list<TaxBracket>  $brackets
 */
function ctx(int $basic = 300000, array $components = [], array $adjustments = [], array $brackets = [], int $employed = 20, float $unpaid = 0, int $absent = 0, int $overtime = 0): PayrollContext
{
    return new PayrollContext(Money::of($basic), 20, $employed, $unpaid, $absent, $overtime, 3.5, $components, $adjustments, $brackets);
}

/**
 * @return array<string, int>
 */
function lineAmounts(PayslipResult $result): array
{
    $out = [];
    foreach ($result->lines as $line) {
        $out[$line->code] = $line->amount->minor;
    }

    return $out;
}

test('basic only', function () {
    $r = calc(ctx());

    expect($r->basic->minor)->toBe(300000)
        ->and($r->gross->minor)->toBe(300000)
        ->and($r->net->minor)->toBe(300000)
        ->and($r->netClamped)->toBeFalse()
        ->and($r->paidDays)->toBe(20.0);
});

test('fixed and percent of basic components', function () {
    $r = calc(ctx(components: [
        new ComponentSpec('HRA', 'House rent', Type::Earning, Method::PercentOfBasic, 5000),
        new ComponentSpec('MED', 'Medical', Type::Earning, Method::Fixed, 20000),
        new ComponentSpec('PF', 'Provident fund', Type::Deduction, Method::PercentOfBasic, 1000),
    ]));

    expect(lineAmounts($r))->toBe(['BASIC' => 300000, 'HRA' => 150000, 'MED' => 20000, 'PF' => 30000])
        ->and($r->gross->minor)->toBe(470000)
        ->and($r->totalDeductions->minor)->toBe(30000)
        ->and($r->net->minor)->toBe(440000);
});

test('per employee override replaces the computed amount', function () {
    $r = calc(ctx(components: [
        new ComponentSpec('HRA', 'House rent', Type::Earning, Method::PercentOfBasic, 5000, override: 1234),
    ]));

    expect(lineAmounts($r)['HRA'])->toBe(1234);
});

test('formula with variables, including overtime hours and min/max/round', function () {
    $r = calc(ctx(basic: 208000, overtime: 120, components: [
        new ComponentSpec('OT', 'Overtime', Type::Earning, Method::Formula, formula: 'overtime_hours * basic / 208 * 1.5'),
        new ComponentSpec('SEN', 'Seniority', Type::Earning, Method::Formula, formula: 'round(min(years_of_service, 5) * 1000)'),
        new ComponentSpec('GR', 'Gross share', Type::Earning, Method::Formula, formula: 'max(gross / 100, 1)'),
    ]));

    expect(lineAmounts($r)['OT'])->toBe(3000)
        ->and(lineAmounts($r)['SEN'])->toBe(3500)
        // gross so far = 208000 + 3000 + 3500
        ->and(lineAmounts($r)['GR'])->toBe(2145);
});

test('formula with unknown variable fails naming the component', function () {
    calc(ctx(components: [
        new ComponentSpec('BAD', 'Bad bonus', Type::Earning, Method::Formula, formula: 'basic * bonus_rate'),
    ]));
})->throws(DomainException::class, 'Bad bonus');

test('formula cannot call constant or enum', function () {
    app(FormulaEvaluator::class)->assertValid("constant('PHP_VERSION')");
})->throws(DomainException::class);

test('progressive tax slabs tax each band only', function () {
    $brackets = [new TaxBracket(0, 100000, 0), new TaxBracket(100000, 300000, 1000), new TaxBracket(300000, null, 2000)];
    $r = calc(ctx(basic: 400000, brackets: $brackets, components: [
        new ComponentSpec('TAX', 'Income tax', Type::Deduction, Method::TaxSlab),
    ]));

    // 0 + 10% of 200000 + 20% of 100000
    expect(lineAmounts($r)['TAX'])->toBe(40000)
        ->and($r->net->minor)->toBe(360000);
});

test('tax is computed on taxable earnings only', function () {
    $brackets = [new TaxBracket(0, null, 1000)];
    $r = calc(ctx(basic: 100000, brackets: $brackets, components: [
        new ComponentSpec('MED', 'Medical (exempt)', Type::Earning, Method::Fixed, 50000, taxable: false),
        new ComponentSpec('BON', 'Allowance', Type::Earning, Method::Fixed, 20000, taxable: true),
        new ComponentSpec('TAX', 'Income tax', Type::Deduction, Method::TaxSlab),
    ]));

    // taxable = basic 100000 + 20000
    expect(lineAmounts($r)['TAX'])->toBe(12000);
});

test('unpaid leave and absent days are deducted from basic', function () {
    $r = calc(ctx(unpaid: 1.5, absent: 1));

    // 300000 / 20 * 2.5
    expect(lineAmounts($r)['UNPAID'])->toBe(37500)
        ->and($r->unpaidDays)->toBe(2.5)
        ->and($r->paidDays)->toBe(17.5)
        ->and($r->net->minor)->toBe(262500);
});

test('joining mid month pro-rates basic and components that depend on it', function () {
    $r = calc(ctx(employed: 10, components: [
        new ComponentSpec('HRA', 'House rent', Type::Earning, Method::PercentOfBasic, 5000),
    ]));

    expect($r->basic->minor)->toBe(150000)
        ->and(lineAmounts($r)['HRA'])->toBe(75000)
        ->and($r->paidDays)->toBe(10.0)
        ->and($r->workingDays)->toBe(20);
});

test('adjustments add earning and deduction lines', function () {
    $r = calc(ctx(adjustments: [
        new AdjustmentSpec('Performance bonus', Type::Earning, Money::of(50000)),
        new AdjustmentSpec('Advance recovery', Type::Deduction, Money::of(10000)),
    ]));

    expect($r->gross->minor)->toBe(350000)
        ->and($r->totalDeductions->minor)->toBe(10000)
        ->and($r->net->minor)->toBe(340000);
});

test('net below zero is clamped and flagged', function () {
    $r = calc(ctx(adjustments: [new AdjustmentSpec('Loan', Type::Deduction, Money::of(400000))]));

    expect($r->net->minor)->toBe(0)
        ->and($r->netClamped)->toBeTrue()
        ->and($r->totalDeductions->minor)->toBe(400000);
});
