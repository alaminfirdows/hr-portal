<?php

namespace Modules\Shared\Contracts;

use Modules\Identity\Models\User;

/**
 * A dashboard card contributed by a module. Rendered by generic frontend widget types.
 */
interface DashboardWidget
{
    public function key(): string;

    public function title(): string;

    /**
     * One of: stat, list, table, action.
     */
    public function type(): string;

    /**
     * Grid columns out of 4.
     */
    public function size(): int;

    public function order(): int;

    public function visible(User $user): bool;

    /**
     * @return array<string, mixed>
     */
    public function data(User $user): array;
}
