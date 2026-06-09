<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        $slug = fake()->unique()->slug(3).'-'.fake()->unique()->numerify('####');

        return [
            'slug' => $slug,
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'project_type' => fake()->randomElement(['demo', 'client', 'personal', 'open_source', 'package']),
            // Default to 'active' to avoid triggering the shipped invariant
            // unintentionally. Use shipped() / archived() / idea() states
            // when those are wanted.
            'status' => 'active',
            'visibility' => 'public',
            'repo_url' => fake()->optional()->url(),
            'live_url' => fake()->optional()->url(),
            'docs_url' => fake()->optional()->url(),
            'short_description' => fake()->sentence(),
            'long_description' => fake()->paragraphs(3, true),
            'long_description_reviewed' => true,
            'client_name' => null,
            'client_industry' => null,
            'shipped_date' => null,
            'hours_estimated' => fake()->numberBetween(20, 200),
            'hours_actual' => null,
            'team_size' => fake()->numberBetween(1, 5),
            'internal_notes' => null,
        ];
    }

    public function shipped(): static
    {
        return $this->state(fn () => [
            'status' => 'shipped',
            'shipped_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'hours_actual' => fake()->numberBetween(20, 300),
        ]);
    }

    public function idea(): static
    {
        return $this->state(['status' => 'idea']);
    }

    public function archived(): static
    {
        return $this->state(['status' => 'archived']);
    }

    public function redacted(): static
    {
        return $this->state([
            'visibility' => 'redacted',
            'client_name' => fake()->company(),
            'client_industry' => fake()->randomElement(['Legal', 'Healthcare', 'E-commerce', 'Education', 'SaaS']),
            'internal_notes' => fake()->paragraph(),
        ]);
    }

    public function private(): static
    {
        return $this->state([
            'visibility' => 'private',
            'internal_notes' => fake()->paragraph(),
        ]);
    }
}
