<?php

namespace Modules\Shared\Data;

use Illuminate\Database\Eloquent\Model;

final readonly class AuditEntry
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function __construct(
        public string $action,
        public string $description,
        public ?Model $subject = null,
        public array $before = [],
        public array $after = [],
        public ?int $actorId = null,
    ) {}
}
