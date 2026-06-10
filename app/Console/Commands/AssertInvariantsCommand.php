<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Sentry\Severity;

/**
 * Nightly safety net for the "one primary per dimension per project"
 * invariant. SetPrimaryTag enforces this eagerly inside a transaction
 * with SELECT … FOR UPDATE; this command finds drift caused by direct
 * pivot writes or migrations that bypassed the action.
 *
 * Exits non-zero on any violation so the cron + BetterStack heartbeat
 * surfaces silence as failure (per Phase 8 §10). Sentry capture wired
 * via the sentry/sentry-laravel package once that's configured (Phase 8).
 */
class AssertInvariantsCommand extends Command
{
    protected $signature = 'codex:assert-invariants';

    protected $description = 'Validates "one primary per dimension per project" across capabilities + technologies. Nightly safety net.';

    public function handle(): int
    {
        $violations = 0;

        foreach (['project_capabilities', 'project_technologies'] as $pivot) {
            $rows = DB::table($pivot)
                ->select('project_id', DB::raw('COUNT(*) AS primary_count'))
                ->where('is_primary', true)
                ->groupBy('project_id')
                ->having('primary_count', '>', 1)
                ->get();

            foreach ($rows as $row) {
                $violations++;
                $this->error(sprintf(
                    '[%s] project %s has %d primary rows (expected ≤ 1)',
                    $pivot,
                    $row->project_id,
                    $row->primary_count,
                ));

                if (app()->bound('sentry')) {
                    app('sentry')->captureMessage(
                        sprintf('codex invariants: %s primary drift on project %s', $pivot, $row->project_id),
                        Severity::warning(),
                    );
                }
            }
        }

        if ($violations === 0) {
            $this->info('codex:assert-invariants — clean (no drift across project_capabilities + project_technologies).');

            return self::SUCCESS;
        }

        $this->error(sprintf('codex:assert-invariants — %d violation(s) found.', $violations));

        return self::FAILURE;
    }
}
