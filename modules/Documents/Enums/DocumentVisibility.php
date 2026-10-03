<?php

namespace Modules\Documents\Enums;

use Modules\Shared\Concerns\HasOptions;

enum DocumentVisibility: string
{
    use HasOptions;

    case Private = 'private';
    case Department = 'department';
    case Team = 'team';
    case Project = 'project';
    case Organization = 'organization';
    case Users = 'users';

    public function color(): string
    {
        return match ($this) {
            self::Private => 'gray',
            self::Department => 'blue',
            self::Team => 'purple',
            self::Project => 'amber',
            self::Organization => 'green',
            self::Users => 'gray',
        };
    }
}
