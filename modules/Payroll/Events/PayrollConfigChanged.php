<?php

namespace Modules\Payroll\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Modules\Shared\Contracts\AuditableEvent;
use Modules\Shared\Data\AuditEntry;

/**
 * Create/update/delete of a pay component, tax slab, assignment set or adjustment.
 */
final class PayrollConfigChanged implements AuditableEvent
{
    use Dispatchable;

    /**
     * @param  'created'|'updated'|'deleted'  $change
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function __construct(
        public readonly Model $subject,
        public readonly string $change,
        public readonly string $label,
        public readonly array $before = [],
        public readonly array $after = [],
        public readonly ?int $actorId = null,
    ) {}

    public function auditEntry(): AuditEntry
    {
        $type = str(class_basename($this->subject))->snake()->toString();

        return new AuditEntry(
            action: "payroll.{$type}.{$this->change}",
            description: ucfirst($this->change).' '.str_replace('_', ' ', $type)." {$this->label}",
            subject: $this->change === 'deleted' ? null : $this->subject,
            before: $this->before,
            after: $this->after,
            actorId: $this->actorId,
        );
    }
}
