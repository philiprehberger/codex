<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table) {
            $table->char('id', 26)->primary()->collation(\App\Support\BinaryCollation::name());

            // null for system actions; FK is intentionally loose — audit
            // history outlives users (force-deleted admin still has a trail).
            $table->char('actor_id', 26)->nullable()->collation(\App\Support\BinaryCollation::name());
            // Forensic surface if TOTP is ever compromised. IPv4 or IPv6.
            $table->string('actor_ip', 45)->nullable();
            $table->string('actor_user_agent', 500)->nullable();

            // create, update, delete, tag_add, tag_remove, merge_capability,
            // unmerge_capability, visibility_change, force_delete, reset_2fa.
            $table->string('action', 60);
            // projects, capabilities, project_capabilities, …
            $table->string('subject_type', 60);
            $table->char('subject_id', 26)->collation(\App\Support\BinaryCollation::name());

            // REQUIRED for merge_capability; nullable for routine
            // create/update/delete. Enforced at the action service layer.
            $table->string('reason', 255)->nullable();

            // {"before": {...}, "after": {...}, "affected_pivots": [...],
            //  "truncated": bool}
            // Capped at 16KB by AuditLogger::write — affected_pivots is the
            // truncatable list, before/after column maps are bounded by the
            // row shape itself.
            $table->json('diff')->nullable();

            $table->timestamp('created_at')->useCurrent();

            // Primary read pattern: audit for one subject row.
            $table->index(['subject_type', 'subject_id']);
            // Secondary: "every merge in the last week".
            $table->index(['action', 'created_at']);
            // Retention sweep + archival.
            $table->index('created_at');
            // Forensic: "every action from this IP since X".
            $table->index(['actor_ip', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
