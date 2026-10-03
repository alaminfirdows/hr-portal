<?php

namespace Modules\Documents\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Documents\Models\Folder;

/**
 * @extends Factory<Folder>
 */
class FolderFactory extends Factory
{
    protected $model = Folder::class;

    public function definition(): array
    {
        return ['name' => fake()->unique()->words(2, true)];
    }
}
