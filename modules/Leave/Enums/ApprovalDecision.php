<?php

namespace Modules\Leave\Enums;

use Modules\Shared\Concerns\HasOptions;

enum ApprovalDecision: string
{
    use HasOptions;

    case Approved = 'approved';
    case Rejected = 'rejected';

    public function color(): string
    {
        return $this === self::Approved ? 'green' : 'red';
    }
}
