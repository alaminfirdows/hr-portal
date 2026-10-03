<?php

namespace Modules\Audit\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Audit\Models\AuditLog;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'action' => 'test.action',
            'description' => fake()->sentence(),
            'before' => [],
            'after' => [],
            'created_at' => now(),
        ];
    }
}
