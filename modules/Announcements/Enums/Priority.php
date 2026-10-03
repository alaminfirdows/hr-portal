<?php

namespace Modules\Announcements\Enums;

use Modules\Shared\Concerns\HasOptions;

enum Priority: string
{
    use HasOptions;

    case Normal = 'normal';
    case Important = 'important';
    case Urgent = 'urgent';

    public function color(): string
    {
        return match ($this) {
            self::Normal => 'gray',
            self::Important => 'amber',
            self::Urgent => 'red',
        };
    }
}
