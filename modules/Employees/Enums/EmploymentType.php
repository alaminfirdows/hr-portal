<?php

namespace Modules\Employees\Enums;

use Modules\Shared\Concerns\HasOptions;

enum EmploymentType: string
{
    use HasOptions;

    case FullTime = 'full_time';
    case PartTime = 'part_time';
    case Contract = 'contract';
    case Intern = 'intern';
}
