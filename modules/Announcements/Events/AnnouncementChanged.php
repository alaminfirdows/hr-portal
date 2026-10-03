<?php

namespace Modules\Announcements\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Announcements\Models\Announcement;
use Modules\Shared\Contracts\AuditableEvent;
use Modules\Shared\Data\AuditEntry;

final class AnnouncementChanged implements AuditableEvent
{
    use Dispatchable;

    /**
     * @param  'created'|'updated'|'deleted'  $change
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function __construct(
        public readonly Announcement $announcement,
        public readonly string $change,
        public readonly ?int $actorId = null,
        public readonly array $before = [],
        public readonly array $after = [],
    ) {}

    public function auditEntry(): AuditEntry
    {
        return new AuditEntry(
            action: "announcements.{$this->change}",
            description: ucfirst($this->change)." announcement {$this->announcement->title}",
            subject: $this->change === 'deleted' ? null : $this->announcement,
            before: $this->before,
            after: $this->after,
            actorId: $this->actorId,
        );
    }
}
