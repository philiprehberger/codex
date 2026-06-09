<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_assets', function (Blueprint $table) {
            $table->char('id', 26)->primary()->collation('utf8mb4_bin');
            $table->char('project_id', 26)->collation('utf8mb4_bin');
            // screenshot, wireframe, logo, diagram, video
            $table->enum('asset_type', ['screenshot', 'wireframe', 'logo', 'diagram', 'video']);
            // Relative to Codex's storage root. The import command copies
            // PNGs into either storage/app/public/projects/<slug>/ or
            // storage/app/private/projects/<slug>/ based on the project's
            // visibility — single source of truth, no symlink ambiguity.
            $table->string('path', 500);
            // Pre-cropped 1200x630 path for static OG-image reuse. Filament
            // asset processor writes it alongside the original; the dashboard
            // SSR layer emits og:image pointing at this path. Phase 1
            // (no /api/og Edge route).
            $table->string('og_path', 500)->nullable();
            $table->string('caption', 500)->nullable();
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'display_order']);

            $table->foreign('project_id')
                ->references('id')->on('projects')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_assets');
    }
};
