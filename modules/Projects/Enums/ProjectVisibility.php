<?php

namespace Modules\Projects\Enums;

use Modules\Shared\Concerns\HasOptions;

/**
 * public: everyone with projects.view. restricted: listed for all, details for members only.
 * private: hidden from non-members.
 */
enum ProjectVisibility: string
{
    use HasOptions;

    case Public = 'public';
    case Restricted = 'restricted';
    case Private = 'private';

    public function color(): string
    {
        return match ($this) {
            self::Public => 'green',
            self::Restricted => 'amber',
            self::Private => 'red',
        };
    }
}
