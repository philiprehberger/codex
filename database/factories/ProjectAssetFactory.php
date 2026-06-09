<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectAsset> */
class ProjectAssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'asset_type' => fake()->randomElement(['screenshot', 'wireframe', 'logo', 'diagram', 'video']),
            'path' => 'projects/'.fake()->slug(2).'/'.fake()->numerify('##').'.png',
            'og_path' => null,
            'caption' => fake()->optional()->sentence(),
            'display_order' => fake()->numberBetween(0, 9),
        ];
    }
}
