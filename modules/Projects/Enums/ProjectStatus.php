<?php

namespace Modules\Projects\Enums;

use Modules\Shared\Concerns\HasOptions;

enum ProjectStatus: string
{
    use HasOptions;

    case Planned = 'planned';
    case Active = 'active';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Archived = 'archived';

    /**
     * Projects that still hold people's time (counted for allocation).
     *
     * @return list<string>
     */
    public static function openValues(): array
    {
        return [self::Planned->value, self::Active->value, self::OnHold->value];
    }

    public function color(): string
    {
        return match ($this) {
            self::Planned => 'blue',
            self::Active => 'green',
            self::OnHold => 'amber',
            self::Completed => 'purple',
            self::Archived => 'gray',
        };
    }
}
