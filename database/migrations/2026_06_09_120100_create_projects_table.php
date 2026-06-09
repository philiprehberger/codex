<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->char('id', 26)->primary()->collation(\App\Support\BinaryCollation::name());
            $table->string('slug', 120)->collation(\App\Support\BinaryCollation::name());
            $table->string('name', 255);

            // demo, client, personal, open_source, package
            $table->enum('project_type', ['demo', 'client', 'personal', 'open_source', 'package']);
            // idea, active, shipped, archived
            $table->enum('status', ['idea', 'active', 'shipped', 'archived']);
            // public, redacted, private
            $table->enum('visibility', ['public', 'redacted', 'private']);
            // drives the 14-day 410-Gone window on asset URLs after a
            // visibility flip (config/codex.php visibility.gone_window_days).
            $table->timestamp('visibility_changed_at')->nullable();

            $table->string('repo_url', 500)->nullable();
            $table->string('live_url', 500)->nullable();
            $table->string('docs_url', 500)->nullable();

            // ≤ 280, optimised for grid cards
            $table->string('short_description', 280);
            // markdown long-form
            $table->mediumText('long_description')->nullable();
            // symmetric with capabilities.description_reviewed. Phase 1
            // hand-written content sets to true on save; Phase 2 LLM-drafted
            // descriptions ship as false and a Filament filter exposes the
            // unreviewed queue.
            $table->boolean('long_description_reviewed')->default(false);

            // Never shown publicly when visibility=redacted (RedactedScope
            // strips). client_industry IS shown publicly even when name
            // redacted — that's the proof-of-portfolio shape.
            $table->string('client_name', 255)->nullable();
            $table->string('client_industry', 120)->nullable();

            // Required when status=shipped — enforced by the model's saving
            // observer (Phase 2 §"Model-level guards"). Schema stays nullable
            // because idea/active rows don't have a ship date.
            $table->date('shipped_date')->nullable();

            $table->integer('hours_estimated')->nullable();
            // Required when status=shipped (same observer rule).
            $table->integer('hours_actual')->nullable();
            $table->integer('team_size')->nullable();

            // Never exposed publicly. RedactedScope strips; $hidden
            // suppresses from serialisation; phpstan-laravel rule blocks
            // raw SQL outside migrations + console commands.
            $table->mediumText('internal_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique('slug');
            $table->index('project_type');
            $table->index('status');
            $table->index('visibility');
            $table->index('shipped_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
