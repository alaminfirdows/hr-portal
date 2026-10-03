<?php

namespace Modules\Shared\Contracts;

use Modules\Shared\Data\AuditEntry;

/**
 * Domain events implementing this are written to the audit log automatically.
 */
interface AuditableEvent
{
    public function auditEntry(): AuditEntry;
}
