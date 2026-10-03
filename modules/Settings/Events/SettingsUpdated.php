<?php

namespace Modules\Settings\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Identity\Models\User;
use Modules\Shared\Contracts\AuditableEvent;
use Modules\Shared\Data\AuditEntry;

final class SettingsUpdated implements AuditableEvent
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function __construct(public readonly array $before, public readonly array $after, public readonly ?User $by) {}

    public function auditEntry(): AuditEntry
    {
        $changedKeys = array_keys(array_filter($this->after, fn ($v, $k) => ($this->before[$k] ?? null) !== $v, ARRAY_FILTER_USE_BOTH));

        return new AuditEntry(
            action: 'settings.updated',
            description: 'Updated organization settings',
            before: array_intersect_key($this->before, array_flip($changedKeys)),
            after: array_intersect_key($this->after, array_flip($changedKeys)),
            actorId: $this->by?->id,
        );
    }
}
