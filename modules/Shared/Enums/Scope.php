<?php

namespace Modules\Shared\Enums;

use Modules\Shared\Concerns\HasOptions;

/**
 * How far a permission reaches over employees.
 */
enum Scope: string
{
    use HasOptions;

    case Own = 'own';
    case Team = 'team';
    case Department = 'department';
    case All = 'all';

    public function rank(): int
    {
        return match ($this) {
            self::Own => 1,
            self::Team => 2,
            self::Department => 3,
            self::All => 4,
        };
    }

    public function covers(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }

    public static function widest(self ...$scopes): ?self
    {
        $widest = null;

        foreach ($scopes as $scope) {
            if ($widest === null || $scope->rank() > $widest->rank()) {
                $widest = $scope;
            }
        }

        return $widest;
    }
}
