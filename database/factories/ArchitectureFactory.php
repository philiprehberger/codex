<?php

namespace Database\Factories;

use App\Models\Architecture;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Architecture> */
class ArchitectureFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->word();
        return [
            'name' => ucfirst($name),
            'slug' => $name.'-'.fake()->unique()->numerify('####'),
            'description' => fake()->sentence(),
        ];
    }
}
