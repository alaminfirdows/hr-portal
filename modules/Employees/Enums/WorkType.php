<?php

namespace Modules\Employees\Enums;

use Modules\Shared\Concerns\HasOptions;

enum WorkType: string
{
    use HasOptions;

    case Onsite = 'onsite';
    case Remote = 'remote';
    case Hybrid = 'hybrid';
}
