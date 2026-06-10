<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Phase 4 — dependency-ordered seed of the real portfolio.
 *
 * Order matters:
 *  1. Vocabulary (capabilities + technologies + industries + …)
 *  2. Projects (demos → packages → redacted client work)
 *
 * Re-running converges via updateOrCreate by slug + sync() on pivots —
 * byte-identical Project::all()->toJson() across runs (Phase 7 test).
 *
 * Production guard: every seeder extends BaseSeeder which aborts unless
 * CODEX_ALLOW_SEEDERS_IN_PRODUCTION=true. SeederGuardServiceProvider is
 * the second layer for anything that forgets to extend BaseSeeder.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Local-dev admin account convenience — production uses
        // codex:seed-admin with explicit email + password.
        if (app()->environment('local', 'testing') && User::count() === 0) {
            User::factory()->create([
                'name' => 'Codex Admin (dev)',
                'email' => 'dev@codex.local',
            ]);
        }

        $this->call([
            CapabilitySeeder::class,
            TechnologySeeder::class,
            IndustrySeeder::class,
            ArchitectureSeeder::class,
            DeliverableSeeder::class,
            DesignStyleSeeder::class,
            DemoProjectsSeeder::class,
            PackagesSeeder::class,
            ClientWorkSeeder::class,
        ]);
    }
}
