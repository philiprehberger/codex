<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectMetric> */
class ProjectMetricFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'recorded_at' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'duration_days' => fake()->numberBetween(1, 60),
            'api_integrations' => fake()->numberBetween(0, 10),
            'database_tables' => fake()->numberBetween(3, 30),
            'test_count' => fake()->numberBetween(0, 200),
            'lighthouse_perf' => fake()->numberBetween(80, 100),
            'lighthouse_a11y' => fake()->numberBetween(85, 100),
            'lighthouse_best' => fake()->numberBetween(80, 100),
            'lighthouse_seo' => fake()->numberBetween(85, 100),
            'loc_total' => fake()->numberBetween(500, 50000),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
