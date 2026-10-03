<?php

namespace Modules\Payroll\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Payroll\Models\PayrollRun;
use Modules\Shared\Contracts\AuditableEvent;
use Modules\Shared\Data\AuditEntry;

/**
 * A payroll run was created or moved through its workflow.
 */
final class PayrollRunChanged implements AuditableEvent
{
    use Dispatchable;

    /**
     * @param  'created'|'calculated'|'reviewed'|'approved'|'processed'  $change
     */
    public function __construct(
        public readonly PayrollRun $run,
        public readonly string $change,
        public readonly ?int $actorId = null,
    ) {}

    public function auditEntry(): AuditEntry
    {
        return new AuditEntry(
            action: "payroll.run.{$this->change}",
            description: ucfirst($this->change)." payroll run {$this->run->period}",
            subject: $this->run,
            after: [
                'status' => $this->run->status->value,
                'employees' => $this->run->employee_count,
                'total_net' => $this->run->total_net,
            ],
            actorId: $this->actorId,
        );
    }
}
