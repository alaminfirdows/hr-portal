<?php

namespace Modules\Leave\Enums;

use Modules\Shared\Concerns\HasOptions;

enum AccrualMethod: string
{
    use HasOptions;

    case Yearly = 'yearly';
    case Monthly = 'monthly';
    case None = 'none';
}
