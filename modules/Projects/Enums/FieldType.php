<?php

namespace Modules\Projects\Enums;

use Modules\Shared\Concerns\HasOptions;

enum FieldType: string
{
    use HasOptions;

    case Text = 'text';
    case Number = 'number';
    case Date = 'date';
    case Select = 'select';
    case Url = 'url';
}
