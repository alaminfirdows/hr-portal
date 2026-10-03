<?php

namespace Modules\AccessControl\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\AccessControl\Models\Role;
use Modules\Identity\Models\User;
use Modules\Shared\Contracts\AuditableEvent;
use Modules\Shared\Data\AuditEntry;

final class RoleSaved implements AuditableEvent
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function __construct(
        public readonly Role $role,
        public readonly array $before,
        public readonly array $after,
        public readonly ?User $by,
    ) {}

    public function auditEntry(): AuditEntry
    {
        return new AuditEntry(
            action: $this->before === [] ? 'access.role.created' : 'access.role.updated',
            description: ($this->before === [] ? 'Created' : 'Updated')." role {$this->role->name}",
            subject: $this->role,
            before: $this->before,
            after: $this->after,
            actorId: $this->by?->id,
        );
    }
}
