<?php

namespace Modules\Audit\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\Audit\Models\AuditLog;
use Modules\Shared\Contracts\AuditTrail;

final class EloquentAuditTrail implements AuditTrail
{
    public function forSubject(Model $subject, int $limit = 50): array
    {
        return array_values(AuditLog::query()
            ->with('actor:id,name')
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->latest('created_at')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'actor' => $log->actor?->name,
                'created_at' => (string) $log->created_at?->toIso8601String(),
                'changes' => $log->changes(),
            ])
            ->all());
    }
}
