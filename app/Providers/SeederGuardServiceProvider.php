<?php

namespace App\Providers;

use Database\Seeders\BaseSeeder;
use Illuminate\Database\Events\NoPendingMigrations;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Second layer of seeder safety. The BaseSeeder constructor handles the
 * happy path; this provider catches the seam where a new seeder forgets
 * to extend BaseSeeder OR a third-party package adds a seeder via its
 * own service provider.
 *
 * Implementation: hooks `Seeder::resolved()` — the framework's container
 * resolution event that fires before any seeder's run() — and re-checks
 * the production guard against the resolved class. If the seeder is a
 * BaseSeeder subclass the constructor already aborted; if not, this is
 * the only line of defence.
 *
 * In practice the surface is small (one CLI entry point in production:
 * php artisan db:seed) so even a single-layer guard would do — the
 * second layer is paranoia, deliberately.
 */
class SeederGuardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Resolve callback runs every time the container instantiates a
        // Seeder. The container is the only path a seeder reaches in
        // production — `php artisan db:seed` resolves through it,
        // `app(SomeSeeder::class)` likewise.
        $this->app->resolving(Seeder::class, function (Seeder $seeder) {
            if ($seeder instanceof BaseSeeder) {
                // BaseSeeder::__construct already gated it.
                return;
            }
            if (App::isProduction() && ! Config::get('codex.seeders.allow_in_production', false)) {
                abort(500, sprintf(
                    'Codex SeederGuard caught %s — it does NOT extend BaseSeeder and '
                    .'tried to run in production without CODEX_ALLOW_SEEDERS_IN_PRODUCTION=true.',
                    $seeder::class,
                ));
            }
        });
    }
}
