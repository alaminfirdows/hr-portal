<?php

namespace Modules\Employees\Enums;

use Modules\Shared\Concerns\HasOptions;

enum Gender: string
{
    use HasOptions;

    case Male = 'male';
    case Female = 'female';
    case Other = 'other';
    case Undisclosed = 'undisclosed';
}
