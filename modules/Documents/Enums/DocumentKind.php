<?php

namespace Modules\Documents\Enums;

use Modules\Shared\Concerns\HasOptions;

enum DocumentKind: string
{
    use HasOptions;

    case File = 'file';
    case Link = 'link';
}
