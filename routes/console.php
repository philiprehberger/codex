<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Codex schedule lives in App\Console\Kernel::schedule() once the
// nightly commands land (Phase 2): codex:assert-invariants,
// codex:audit-slug-collisions, codex:export-portfolio. The
// codex:archive-audit-log (Phase 8) joins later.
