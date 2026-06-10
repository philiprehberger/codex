<?php

namespace Database\Seeders;

use App\Models\Capability;
use App\Models\Package;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Sister-table seeder. Per-package rows in the `packages` table,
 * NOT in the `projects` table — keeps the heatmap project-scoped.
 *
 * Source: database/fixtures/packages.json — committed for CI + prod
 * runs. Refresh locally with `php artisan codex:ingest-packages`
 * (walks ~/projects/packages/).
 *
 * Idempotent via updateOrCreate(slug). sync() on the capability
 * pivot drops manually-added Filament tags on re-run — production
 * never re-seeds (BaseSeeder + SeederGuardServiceProvider).
 */
class PackagesSeeder extends BaseSeeder
{
    public function run(): void
    {
        $fixturePath = base_path('database/fixtures/packages.json');
        if (! File::exists($fixturePath)) {
            $this->command->warn("PackagesSeeder: {$fixturePath} not found — skipping. Run codex:ingest-packages first.");

            return;
        }

        $rows = json_decode(File::get($fixturePath), true);
        if (! is_array($rows)) {
            $this->command->error("PackagesSeeder: malformed fixture at {$fixturePath}");

            return;
        }

        // Pre-fetch capabilities by slug for the pivot sync.
        $capsBySlug = Capability::query()->pluck('id', 'slug')->all();

        $this->command->info('PackagesSeeder: ingesting '.count($rows).' rows');

        $progressBar = $this->command->getOutput()->createProgressBar(count($rows));
        $progressBar->start();

        foreach ($rows as $row) {
            DB::transaction(function () use ($row, $capsBySlug) {
                $package = Package::updateOrCreate(
                    ['slug' => $row['slug']],
                    [
                        'name' => $row['name'],
                        'language' => $row['language'],
                        'registry' => $row['registry'],
                        'status' => $row['status'] ?? 'active',
                        'short_description' => $row['short_description'],
                        'long_description_reviewed' => true,
                        'repo_url' => $row['repo_url'] ?? null,
                        'registry_url' => $row['registry_url'] ?? null,
                        'docs_url' => $row['docs_url'] ?? null,
                    ],
                );

                $capabilitySlugs = $row['capability_slugs'] ?? [];
                $capabilityIds = array_values(array_filter(array_map(
                    fn (string $slug) => $capsBySlug[$slug] ?? null,
                    $capabilitySlugs,
                )));
                $package->capabilities()->sync($capabilityIds);

                // Mark the primary capability if known.
                if (! empty($row['primary_capability']) && isset($capsBySlug[$row['primary_capability']])) {
                    DB::table('package_capabilities')
                        ->where('package_id', $package->id)
                        ->update(['is_primary' => false]);
                    DB::table('package_capabilities')
                        ->where('package_id', $package->id)
                        ->where('capability_id', $capsBySlug[$row['primary_capability']])
                        ->update(['is_primary' => true]);
                }
            });
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->command->newLine();
    }
}
