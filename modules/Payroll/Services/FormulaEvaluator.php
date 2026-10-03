<?php

namespace Modules\Payroll\Services;

use Modules\Shared\Exceptions\DomainException;
use Throwable;

/**
 * Compiles and evaluates payroll formulas. Money variables are minor units, so a formula that
 * returns money returns minor units as well.
 */
final class FormulaEvaluator
{
    /**
     * Variables a formula may use.
     */
    public const VARIABLES = [
        'basic', 'gross', 'working_days', 'paid_days', 'unpaid_leave_days', 'absent_days', 'overtime_hours', 'years_of_service',
    ];

    private readonly SafeExpressionLanguage $language;

    public function __construct()
    {
        $this->language = new SafeExpressionLanguage;
    }

    /**
     * @throws DomainException when the formula does not compile or uses an unknown variable
     */
    public function assertValid(string $formula): void
    {
        try {
            $this->language->lint($formula, self::VARIABLES);
        } catch (Throwable $e) {
            throw new DomainException(__('Invalid formula: :error', ['error' => $e->getMessage()]), 'formula');
        }
    }

    /**
     * @param  array<string, int|float>  $variables
     *
     * @throws DomainException
     */
    public function evaluate(string $formula, array $variables): float
    {
        $this->assertValid($formula);

        try {
            $result = $this->language->evaluate($formula, $variables);
        } catch (Throwable $e) {
            throw new DomainException(__('Invalid formula: :error', ['error' => $e->getMessage()]), 'formula');
        }

        $number = is_int($result) || is_float($result) ? (float) $result : NAN;

        if (is_nan($number) || is_infinite($number)) {
            throw new DomainException(__('Invalid formula: result is not a finite number.'), 'formula');
        }

        return $number;
    }
}
