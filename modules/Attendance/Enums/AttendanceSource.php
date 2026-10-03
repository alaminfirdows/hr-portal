<?php

namespace Modules\Attendance\Enums;

use Modules\Shared\Concerns\HasOptions;

enum AttendanceSource: string
{
    use HasOptions;

    case Web = 'web';
    case Manual = 'manual';
    case System = 'system';
}
