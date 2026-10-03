<?php

namespace Modules\AccessControl\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Identity\Models\User;
use Modules\Shared\Contracts\AuditableEvent;
use Modules\Shared\Data\AuditEntry;

final class RolesAssigned implements AuditableEvent
{
    use Dispatchable;

    /**
     * @param  list<string>  $before
     * @param  list<string>  $after
     */
    public function __construct(
        public readonly User $user,
        public readonly array $before,
        public readonly array $after,
        public readonly ?User $by,
    ) {}

    public function auditEntry(): AuditEntry
    {
        return new AuditEntry(
            action: 'access.roles.assigned',
            description: "Changed roles of {$this->user->name}",
            subject: $this->user,
            before: ['roles' => $this->before],
            after: ['roles' => $this->after],
            actorId: $this->by?->id,
        );
    }
}
