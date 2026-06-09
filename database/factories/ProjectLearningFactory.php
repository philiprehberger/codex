<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectLearning;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectLearning> */
class ProjectLearningFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->sentence(),
            'description' => fake()->paragraphs(2, true),
            'visibility' => fake()->randomElement(['public', 'private']),
        ];
    }
}
