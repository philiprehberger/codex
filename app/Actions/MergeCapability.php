<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Capability;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Validation\ValidationException;

/**
 * Merges capability $source into capability $target. The plan calls this
 * the "vocabulary moderator": deletes are forbidden (Filament removes
 * the delete action; ON DELETE RESTRICT on the pivot is the schema-level
 * floor), so merge is the only way to fold "User Authentication" into
 * "Authentication" without losing tagged-project history.
 *
 * Enforces:
 *  - terminal-canonical rewriting: if $target itself is an alias, fall
 *    back to its terminal canonical so reads always resolve in one hop
 *    (in practice this is rejected by canBeMergedInto, but the safety
 *    net is here too)
 *  - cycle prevention (Capability::canBeMergedInto)
 *  - alias-target rejection (Capability::canBeMergedInto)
 *  - reason required (≤ 255 chars), audited
 *  - audit_log entry with diff.before.canonical_id so an admin can
 *    unmerge via the audit-log resource action (Phase 3)
 */
class MergeCapability
{
    public function execute(Capability $source, Capability $target, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => ['A reason is required for capability merges.'],
            ]);
        }
        if (strlen($reason) > 255) {
            throw ValidationException::withMessages([
                'reason' => ['Reason must be at most 255 characters.'],
            ]);
        }

        $eligibility = $source->canBeMergedInto($target);
        if (! $eligibility->allowed) {
            throw ValidationException::withMessages([
                'target' => [$eligibility->reason ?? 'Merge not allowed.'],
            ]);
        }

        $terminal = $target->resolveCanonical();
        $previousCanonicalId = $source->canonical_id;

        DB::transaction(function () use ($source, $terminal, $reason, $previousCanonicalId) {
            $source->canonical_id = $terminal->id;
            $source->save();

            AuditLog::create([
                'actor_id' => Auth::id(),
                'actor_ip' => Request::ip(),
                'actor_user_agent' => Request::userAgent(),
                'action' => 'merge_capability',
                'subject_type' => 'capabilities',
                'subject_id' => $source->id,
                'reason' => $reason,
                'diff' => [
                    'before' => ['canonical_id' => $previousCanonicalId],
                    'after'  => ['canonical_id' => $terminal->id],
                    // affected_pivots stays empty in Phase 1 because reads
                    // use COALESCE(canonical_id, id) and pivot rows aren't
                    // rewritten. The key is present in the shape so a
                    // Phase 2 denormalisation that rewrites pivots at merge
                    // time can populate it without a schema change.
                    'affected_pivots' => [],
                    'truncated' => false,
                ],
                'created_at' => now(),
            ]);
        });
    }
}
