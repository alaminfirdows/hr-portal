<?php

namespace Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Identity\Models\User;
use Modules\Shared\Contracts\AuditableEvent;
use Modules\Shared\Data\AuditEntry;

final class InvitationAccepted implements AuditableEvent
{
    use Dispatchable;

    public function __construct(public readonly User $user) {}

    public function auditEntry(): AuditEntry
    {
        return new AuditEntry(
            action: 'identity.invitation.accepted',
            description: "{$this->user->name} accepted the invitation",
            subject: $this->user,
            actorId: $this->user->id,
        );
    }
}
