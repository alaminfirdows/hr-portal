<?php

namespace Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Identity\Models\User;
use Modules\Shared\Contracts\AuditableEvent;
use Modules\Shared\Data\AuditEntry;

final class UserInvited implements AuditableEvent
{
    use Dispatchable;

    public function __construct(public readonly User $user, public readonly ?User $by) {}

    public function auditEntry(): AuditEntry
    {
        return new AuditEntry(
            action: 'identity.user.invited',
            description: "Invited {$this->user->email}",
            subject: $this->user,
            after: ['email' => $this->user->email],
            actorId: $this->by?->id,
        );
    }
}
