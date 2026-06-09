<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capabilities', function (Blueprint $table) {
            $table->char('id', 26)->primary()->collation('utf8mb4_bin');
            $table->string('name', 120);
            $table->string('slug', 120)->collation('utf8mb4_bin');
            // User Mgmt, Commerce, Marketing, Content, Analytics, Integrations,
            // Automation, AI, Infrastructure
            $table->string('category', 60);
            // 1-2 paragraphs, copy-paste-into-proposal-ready
            $table->text('description');
            // LLM-seeded descriptions ship as false; hand-edit flips to true.
            // Filament filter exposes the unreviewed queue (Phase 3).
            $table->boolean('description_reviewed')->default(false);
            // lucide icon name
            $table->string('icon', 60)->nullable();
            // vocabulary moderation: when set, this row is an alias for the
            // referenced canonical capability. Reads resolve via COALESCE
            // (Phase 5 aggregation strategy). ON DELETE SET NULL so a
            // canonical row deletion doesn't break aliased history — but
            // RESTRICT on the pivot side means deletion can't actually fire
            // while tagged.
            $table->char('canonical_id', 26)->nullable()->collation('utf8mb4_bin');
            $table->timestamps();

            $table->unique('slug');
            $table->index('canonical_id');
            $table->index('category');

            $table->foreign('canonical_id')
                ->references('id')->on('capabilities')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('capabilities', function (Blueprint $table) {
            $table->dropForeign(['canonical_id']);
        });
        Schema::dropIfExists('capabilities');
    }
};
