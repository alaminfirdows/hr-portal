<?php

namespace Modules\Documents\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Documents\Models\Document;
use Modules\Shared\Contracts\AuditableEvent;
use Modules\Shared\Data\AuditEntry;

/**
 * Document uploaded, updated, shared or deleted.
 */
final class DocumentChanged implements AuditableEvent
{
    use Dispatchable;

    /**
     * @param  'created'|'updated'|'shared'|'deleted'  $change
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function __construct(
        public readonly Document $document,
        public readonly string $change,
        public readonly ?int $actorId = null,
        public readonly array $before = [],
        public readonly array $after = [],
    ) {}

    public function auditEntry(): AuditEntry
    {
        return new AuditEntry(
            action: "documents.{$this->change}",
            description: ucfirst($this->change)." document {$this->document->title}",
            subject: $this->change === 'deleted' ? null : $this->document,
            before: $this->before,
            after: $this->after,
            actorId: $this->actorId,
        );
    }
}
