<?php

namespace Tests\Feature;

use App\Actions\MergeCapability;
use App\Models\AuditLog;
use App\Models\Capability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 2 DoD (c): merging A→B then B→C lands the chain at C with one
 * hop (terminal-canonical rule). Also covers the cycle, self-merge, and
 * empty-reason rejection paths so the merge surface is exercised end-to-end.
 */
class CapabilityMergeTest extends TestCase
{
    use RefreshDatabase;

    private MergeCapability $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new MergeCapability();
    }

    public function test_merging_via_an_alias_target_rewrites_to_terminal_canonical(): void
    {
        $a = Capability::factory()->create(['slug' => 'cap-a']);
        $b = Capability::factory()->create(['slug' => 'cap-b']);
        $c = Capability::factory()->create(['slug' => 'cap-c']);

        // B → A
        $this->action->execute($b, $a, 'Folding User Authentication into Authentication');
        // C → B (B is now an alias of A) — the action must rewrite to A,
        // not stop at B. Read-side resolution is always one hop.
        $this->action->execute($c, $b->fresh(), 'Folding Account Authentication via alias');

        $b->refresh();
        $c->refresh();

        $this->assertNull($a->fresh()->canonical_id, 'A stays canonical.');
        $this->assertSame($a->id, $b->canonical_id, 'B points at A.');
        $this->assertSame(
            $a->id,
            $c->canonical_id,
            'C points at A (terminal), NOT at the intermediate alias B.',
        );
        $this->assertSame($a->id, $c->resolveCanonical()->id);

        $this->assertSame(2, AuditLog::query()->where('action', 'merge_capability')->count());
    }

    public function test_cycle_merge_is_rejected(): void
    {
        $a = Capability::factory()->create();
        $b = Capability::factory()->create();

        $this->action->execute($b, $a, 'B into A');

        // A → B would resolve B's canonical chain to A, then loop.
        $this->expectException(ValidationException::class);
        $this->action->execute($a, $b->fresh(), 'cycle attempt');
    }

    public function test_self_merge_is_rejected(): void
    {
        $a = Capability::factory()->create();

        $this->expectException(ValidationException::class);
        $this->action->execute($a, $a, 'self attempt');
    }

    public function test_empty_reason_is_rejected(): void
    {
        $source = Capability::factory()->create();
        $target = Capability::factory()->create();

        $this->expectException(ValidationException::class);
        $this->action->execute($source, $target, '   ');
    }

    public function test_audit_log_captures_before_canonical_and_reason(): void
    {
        $a = Capability::factory()->create();
        $b = Capability::factory()->create();

        $this->action->execute($b, $a, 'documented reason');

        $log = AuditLog::query()
            ->where('action', 'merge_capability')
            ->where('subject_id', $b->id)
            ->firstOrFail();

        $this->assertSame('documented reason', $log->reason);
        $this->assertNull($log->diff['before']['canonical_id']);
        $this->assertSame($a->id, $log->diff['after']['canonical_id']);
        $this->assertSame([], $log->diff['affected_pivots']);
        $this->assertFalse($log->diff['truncated']);
    }
}
