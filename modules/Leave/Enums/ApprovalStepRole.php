<?php

namespace Modules\Leave\Enums;

use Modules\Shared\Concerns\HasOptions;

enum ApprovalStepRole: string
{
    use HasOptions;

    case Manager = 'manager';
    case Hr = 'hr';

    public function label(): string
    {
        return match ($this) {
            self::Manager => 'Manager',
            self::Hr => 'HR',
        };
    }
}
