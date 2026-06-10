<?php

namespace App\Models\Pivots;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Base pivot for the seven project_* pivot tables. Adds HasUlids so
 * attach() / sync() / save() emit a ULID into the `id` column (which
 * is the pivot's PK). Audit-log diffs can then reference a specific
 * pivot row by its ULID — load-bearing for the Phase 2 affected_pivots
 * shape and any Phase 2-future denormalisation.
 *
 * Pivots that carry is_primary (project_technologies, project_capabilities)
 * subclass this with a saved-observer in Phase 3 to enforce the
 * "at most one primary per dimension" invariant on direct writes.
 */
abstract class CodexPivot extends Pivot
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * HasUlids defaults to ['id']; this is just explicit so the contract
     * doesn't drift when subclasses override.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['id'];
    }
}
