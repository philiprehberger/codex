<?php

namespace Database\Factories;

use App\Models\DesignStyle;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DesignStyle> */
class DesignStyleFactory extends Factory
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
