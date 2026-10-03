<?php

namespace Modules\Payroll\Services;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * Expression language limited to arithmetic plus min, max, round. The stock `constant` and `enum`
 * functions are not registered so formulas cannot read PHP constants or classes.
 */
final class SafeExpressionLanguage extends ExpressionLanguage
{
    protected function registerFunctions(): void
    {
        foreach (['min', 'max', 'round'] as $function) {
            $this->addFunction(ExpressionFunction::fromPhp($function));
        }
    }
}
