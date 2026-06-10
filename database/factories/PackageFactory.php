<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Package> */
class PackageFactory extends Factory
{
    public function definition(): array
    {
        $slug = fake()->unique()->slug(3).'-'.fake()->unique()->numerify('####');
        $language = fake()->randomElement(['typescript', 'php', 'python', 'go', 'rust', 'ruby']);
        $registry = match ($language) {
            'typescript' => 'npm',
            'php' => 'packagist',
            'python' => 'pypi',
            'go' => 'go',
            'rust' => 'cargo',
            'ruby' => 'rubygems',
            default => 'npm',
        };

        return [
            'slug' => $slug,
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'language' => $language,
            'registry' => $registry,
            'status' => 'active',
            'short_description' => fake()->sentence(),
            'long_description' => fake()->paragraph(),
            'long_description_reviewed' => true,
            'repo_url' => 'https://github.com/philiprehberger/'.$slug,
            'registry_url' => null,
            'docs_url' => null,
            'shipped_date' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
        ];
    }

    public function archived(): static
    {
        return $this->state(['status' => 'archived']);
    }
}
