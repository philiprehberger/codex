<?php

namespace App\Console\Commands;

use App\Models\Package;
use App\Services\CacheInvalidator;
use App\Services\GitHubReadmeFetcher;
use Illuminate\Console\Command;

/**
 * Populates packages.readme_markdown from GitHub.
 *
 * Default behaviour: skip packages whose readme_fetched_at is within
 * the staleness window (7 days). --force ignores the window and
 * refetches everything. --slug=<slug> targets one package — useful
 * when adding a single package or debugging a parse failure.
 *
 * Invalidates the report caches at the end so the dashboard's
 * /api/v1/packages/{slug} response picks up the new readme on the
 * next request.
 */
class FetchPackageReadmesCommand extends Command
{
    protected $signature = 'codex:fetch-package-readmes
                            {--force : Refetch even if readme_fetched_at is fresh}
                            {--slug= : Only fetch the package with this slug}
                            {--days=7 : Staleness threshold in days (default 7)}';

    protected $description = 'Pull README markdown from GitHub for every package with a repo_url.';

    public function handle(GitHubReadmeFetcher $fetcher, CacheInvalidator $invalidator): int
    {
        $query = Package::query()->whereNotNull('repo_url');

        if (is_string($slug = $this->option('slug')) && $slug !== '') {
            $query->where('slug', $slug);
        } elseif (! $this->option('force')) {
            $days = (int) $this->option('days');
            $cutoff = now()->subDays($days);
            $query->where(function ($q) use ($cutoff) {
                $q->whereNull('readme_fetched_at')->orWhere('readme_fetched_at', '<', $cutoff);
            });
        }

        $packages = $query->orderBy('id')->get();
        $total = $packages->count();
        if ($total === 0) {
            $this->info('No packages to fetch.');

            return self::SUCCESS;
        }

        $this->info("Fetching READMEs for {$total} package(s)…");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $fetched = 0;
        $skipped = 0;
        $errors = 0;
        foreach ($packages as $package) {
            $repoUrl = (string) $package->repo_url;
            try {
                $readme = $fetcher->fetch($repoUrl);
                if ($readme === null) {
                    $skipped++;
                } else {
                    $package->readme_markdown = $readme;
                    $fetched++;
                }
                $package->readme_fetched_at = now();
                $package->save();
            } catch (\Throwable $e) {
                $errors++;
                $this->newLine();
                $this->error("  {$package->slug}: {$e->getMessage()}");
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);

        $this->info("Fetched: {$fetched}");
        $this->info("Skipped (no README / 404): {$skipped}");
        if ($errors > 0) {
            $this->warn("Errors: {$errors}");
        }

        $invalidator->forgetReports();

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }
}
