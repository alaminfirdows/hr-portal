<?php

namespace Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Identity\Models\User;
use Modules\Shared\Contracts\AuditableEvent;
use Modules\Shared\Data\AuditEntry;

final class UserActivationChanged implements AuditableEvent
{
    use Dispatchable;

    public function __construct(public readonly User $user, public readonly ?User $by) {}

    public function auditEntry(): AuditEntry
    {
        $state = $this->user->is_active ? 'activated' : 'deactivated';

        return new AuditEntry(
            action: "identity.user.{$state}",
            description: ucfirst($state)." {$this->user->email}",
            subject: $this->user,
            before: ['is_active' => ! $this->user->is_active],
            after: ['is_active' => $this->user->is_active],
            actorId: $this->by?->id,
        );
    }
}
