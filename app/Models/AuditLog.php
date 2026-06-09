<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only-ish — written by AuditLogger::write() (Phase 3) and the
 * MergeCapability / SetPrimaryTag actions. Filament exposes a view-only
 * resource. The unmerge action reads diff.before.canonical_id to restore
 * state.
 */
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory, HasUlids;

    // Singular table name — load-bearing, matches the plan's schema spec
    // and the "log of audited actions" idiomatic naming.
    protected $table = 'audit_log';

    public $timestamps = false;

    protected $fillable = [
        'actor_id', 'actor_ip', 'actor_user_agent',
        'action', 'subject_type', 'subject_id',
        'reason', 'diff', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'diff' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
