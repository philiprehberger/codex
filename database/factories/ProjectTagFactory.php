<?php

namespace Database\Factories;

use App\Models\ProjectTag;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectTag> */
class ProjectTagFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->word();
        return [
            'name' => ucfirst($name),
            'slug' => $name.'-'.fake()->unique()->numerify('####'),
        ];
    }
}
