<?php

namespace Database\Factories;

use App\Models\Capability;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Capability> */
class CapabilityFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);
        return [
            'name' => ucfirst($name),
            'slug' => str_replace(' ', '-', $name).'-'.fake()->unique()->numerify('####'),
            'category' => fake()->randomElement([
                'UserMgmt', 'Commerce', 'Marketing', 'Content',
                'Analytics', 'Integrations', 'Automation', 'AI', 'Infrastructure',
            ]),
            'description' => fake()->paragraph(),
            'description_reviewed' => false,
            'icon' => fake()->randomElement(['shield', 'lock', 'key', 'mail', 'bell', null]),
            'canonical_id' => null,
        ];
    }

    /** Mark as a reviewed capability (hand-edited description). */
    public function reviewed(): static
    {
        return $this->state(['description_reviewed' => true]);
    }
}
