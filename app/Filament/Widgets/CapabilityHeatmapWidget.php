<?php

namespace App\Filament\Widgets;

use App\Models\Capability;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Dashboard widget — quick summary of vocabulary state. Stats only for
 * the Phase 3 dashboard; the rich capability × project matrix lives at
 * /heatmap on the Next.js dashboard (Phase 6).
 */
class CapabilityHeatmapWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $total = Capability::count();
        $cap = (int) config('codex.vocabulary.capabilities.cap', 80);
        $warn = (int) config('codex.vocabulary.capabilities.warn', 60);
        $unreviewed = Capability::where('description_reviewed', false)->count();
        $aliases = Capability::whereNotNull('canonical_id')->count();
        $canonical = $total - $aliases;

        $capStatColor = match (true) {
            $total >= $cap => 'danger',
            $total >= $warn => 'warning',
            default => 'success',
        };

        return [
            Stat::make('Capabilities', "{$canonical} canonical")
                ->description("of {$total} total — {$aliases} aliased into canonicals")
                ->color($capStatColor),
            Stat::make('Vocabulary cap', "{$total} / {$cap}")
                ->description("warn at {$warn} · cap at {$cap}")
                ->color($capStatColor),
            Stat::make('Unreviewed descriptions', (string) $unreviewed)
                ->description($unreviewed === 0 ? 'all reviewed' : 'queue them via the description_reviewed filter')
                ->color($unreviewed === 0 ? 'success' : 'warning'),
        ];
    }
}
