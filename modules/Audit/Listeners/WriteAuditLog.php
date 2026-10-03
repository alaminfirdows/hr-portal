<?php

namespace Modules\Audit\Listeners;

use Modules\Audit\Services\AuditLogger;
use Modules\Shared\Contracts\AuditableEvent;

/**
 * Wildcard listener: every dispatched AuditableEvent becomes an audit_logs row.
 */
final class WriteAuditLog
{
    public function __construct(private readonly AuditLogger $logger) {}

    /**
     * @param  array<int, mixed>  $payload
     */
    public function handle(string $eventName, array $payload): void
    {
        $event = $payload[0] ?? null;

        if ($event instanceof AuditableEvent) {
            $this->logger->log($event->auditEntry());
        }
    }
}
