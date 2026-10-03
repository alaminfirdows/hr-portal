<?php

namespace Modules\Shared\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Read access to audit history of a subject. Implemented by the Audit module.
 */
interface AuditTrail
{
    /**
     * @return list<array{id: int, action: string, description: string, actor: ?string, created_at: string, changes: array<string, array{from: mixed, to: mixed}>}>
     */
    public function forSubject(Model $subject, int $limit = 50): array;
}
