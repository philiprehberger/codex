<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

/**
 * Production-guard floor. Every seeder must extend this base — the
 * constructor aborts if APP_ENV=production and the explicit escape
 * hatch (codex.seeders.allow_in_production) isn't set.
 *
 * The escape hatch only flips for the single --first-deploy run of the
 * deploy script per Phase 8 §8. Once admin has curated capabilities
 * via Filament, re-seeding would clobber the curation via sync(). The
 * deploy script sets CODEX_ALLOW_SEEDERS_IN_PRODUCTION=true for that
 * one run.
 *
 * Defence-in-depth: SeederGuardServiceProvider re-checks every seeder
 * invocation at the framework hook surface, so a seeder that forgets to
 * extend this base or a third-party service provider's seeder still
 * trips the guard.
 */
abstract class BaseSeeder extends Seeder
{
    public function __construct()
    {
        $this->assertSafeEnvironment();
    }

    protected function assertSafeEnvironment(): void
    {
        if (App::isProduction() && ! Config::get('codex.seeders.allow_in_production', false)) {
            abort(500, sprintf(
                'Codex seeders are dev-only by default. %s tried to run in production without '
                .'CODEX_ALLOW_SEEDERS_IN_PRODUCTION=true.',
                static::class,
            ));
        }
    }
}
