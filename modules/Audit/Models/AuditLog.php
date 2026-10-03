<?php

namespace Modules\Audit\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Audit\Database\Factories\AuditLogFactory;
use Modules\Identity\Models\User;

/**
 * Append-only record of who did what, when, from where, with before/after values.
 *
 * @property int $id
 * @property int|null $actor_id
 * @property string $action
 * @property string $description
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property CarbonImmutable|null $created_at
 * @property-read User|null $actor
 */
#[Fillable(['actor_id', 'action', 'description', 'subject_type', 'subject_id', 'before', 'after', 'ip_address', 'user_agent', 'created_at'])]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Field => {from, to} for keys present in either side.
     *
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public function changes(): array
    {
        $before = $this->before ?? [];
        $after = $this->after ?? [];
        $changes = [];

        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
            $changes[$key] = ['from' => $before[$key] ?? null, 'to' => $after[$key] ?? null];
        }

        return $changes;
    }
}
