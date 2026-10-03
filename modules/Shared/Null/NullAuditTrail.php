<?php

namespace Modules\Shared\Null;

use Illuminate\Database\Eloquent\Model;
use Modules\Shared\Contracts\AuditTrail;

final class NullAuditTrail implements AuditTrail
{
    public function forSubject(Model $subject, int $limit = 50): array
    {
        return [];
    }
}
