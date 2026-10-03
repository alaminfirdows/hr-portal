<?php

namespace Modules\Leave\Enums;

use Modules\Shared\Concerns\HasOptions;

enum DayPart: string
{
    use HasOptions;

    case Full = 'full';
    case FirstHalf = 'first_half';
    case SecondHalf = 'second_half';

    public function isHalf(): bool
    {
        return $this !== self::Full;
    }
}
