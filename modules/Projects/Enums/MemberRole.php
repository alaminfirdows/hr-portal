<?php

namespace Modules\Projects\Enums;

use Modules\Shared\Concerns\HasOptions;

enum MemberRole: string
{
    use HasOptions;

    case Lead = 'lead';
    case Member = 'member';
    case Viewer = 'viewer';
}
