<?php

namespace Database\Factories;

use App\Models\Technology;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Technology> */
class TechnologyFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => ucfirst($name),
            'slug' => $name.'-'.fake()->unique()->numerify('####'),
            'category' => fake()->randomElement([
                'language', 'framework', 'cms', 'database',
                'infrastructure', 'cloud', 'tooling', 'api', 'library',
            ]),
        ];
    }
}
