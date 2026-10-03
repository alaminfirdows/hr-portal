<?php

namespace Modules\AccessControl\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\AccessControl\Models\Role;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        $name = Str::title(fake()->unique()->word().' '.fake()->word());

        return [
            'name' => $name,
            'slug' => Str::slug($name, '_'),
            'description' => null,
            'is_super' => false,
            'is_system' => false,
        ];
    }

    public function super(): static
    {
        return $this->state(['is_super' => true]);
    }
}
