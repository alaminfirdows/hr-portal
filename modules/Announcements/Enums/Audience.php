<?php

namespace Modules\Announcements\Enums;

use Modules\Shared\Concerns\HasOptions;

enum Audience: string
{
    use HasOptions;

    case All = 'all';
    case Department = 'department';
    case Team = 'team';
    case Employees = 'employees';
}
