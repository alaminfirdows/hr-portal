<?php

namespace Modules\Leave\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Leave\Models\LeaveBalance;
use Modules\Shared\Contracts\AuditableEvent;
use Modules\Shared\Data\AuditEntry;

final class LeaveBalanceAdjusted implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly LeaveBalance $balance,
        public readonly float $before,
        public readonly string $reason,
        public readonly ?int $actorId = null,
    ) {}

    public function auditEntry(): AuditEntry
    {
        $b = $this->balance;

        return new AuditEntry(
            action: 'leave.balance_adjusted',
            description: "Adjusted {$b->type->name} balance ({$b->year}) of {$b->employee->full_name} by ".($b->adjustment - $this->before),
            subject: $b,
            before: ['adjustment' => $this->before],
            after: ['adjustment' => $b->adjustment, 'reason' => $this->reason],
            actorId: $this->actorId,
        );
    }
}
