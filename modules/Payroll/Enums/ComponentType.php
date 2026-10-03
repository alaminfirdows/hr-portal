<?php

namespace Modules\Payroll\Enums;

use Modules\Shared\Concerns\HasOptions;

enum ComponentType: string
{
    use HasOptions;

    case Earning = 'earning';
    case Deduction = 'deduction';

    public function color(): string
    {
        return $this === self::Earning ? 'green' : 'amber';
    }
}
