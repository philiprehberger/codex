<?php

namespace Database\Factories;

use App\Models\Deliverable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Deliverable> */
class DeliverableFactory extends Factory
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
