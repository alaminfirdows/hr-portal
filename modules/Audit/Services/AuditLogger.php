<?php

namespace Modules\Audit\Services;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Audit\Models\AuditLog;
use Modules\Shared\Data\AuditEntry;

final class AuditLogger
{
    /**
     * Never stored, even when an event includes them.
     */
    private const REDACTED = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'token'];

    public function __construct(private readonly Request $request) {}

    public function log(AuditEntry $entry): AuditLog
    {
        $actorId = $entry->actorId ?? $this->request->user()?->getAuthIdentifier();

        return AuditLog::query()->create([
            'actor_id' => $actorId,
            'action' => $entry->action,
            'description' => Str::limit($entry->description, 497),
            'subject_type' => $entry->subject?->getMorphClass(),
            'subject_id' => $entry->subject?->getKey(),
            'before' => $this->clean($entry->before),
            'after' => $this->clean($entry->after),
            'ip_address' => app()->runningInConsole() ? null : $this->request->ip(),
            'user_agent' => app()->runningInConsole() ? 'console' : Str::limit((string) $this->request->userAgent(), 250, ''),
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function clean(array $values): array
    {
        $clean = [];

        foreach ($values as $key => $value) {
            $clean[$key] = match (true) {
                in_array($key, self::REDACTED, true) => '[redacted]',
                $value instanceof BackedEnum => $value->value,
                $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
                is_array($value) => $this->clean($value),
                is_object($value) => method_exists($value, '__toString') ? (string) $value : $value::class,
                default => $value,
            };
        }

        return $clean;
    }
}
