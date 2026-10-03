<?php

namespace Modules\Projects\Enums;

use Modules\Shared\Concerns\HasOptions;

enum ProjectPriority: string
{
    use HasOptions;

    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function color(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Medium => 'blue',
            self::High => 'amber',
            self::Critical => 'red',
        };
    }
}
