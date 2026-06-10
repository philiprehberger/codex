<?php

use App\Support\BinaryCollation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per plan §"Sister-table for packages" (Phase 2 deferred → now).
 *
 * `packages` is parallel to `projects` — same shape of "Philip shipped
 * this thing", different cardinality (~635 packages vs ~70 projects).
 * Sister-table avoids the heatmap-noise problem the plan named: the
 * heatmap stays project-scoped; package detail lives in its own
 * surface (/api/v1/packages, /packages on the dashboard).
 *
 * Capabilities are shared with projects via the `capabilities` table
 * and a dedicated `package_capabilities` pivot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->char('id', 26)->primary()->collation(BinaryCollation::name());
            $table->string('slug', 120)->collation(BinaryCollation::name());
            $table->string('name', 255);

            // Language slug, references the technologies table by slug
            // (NOT a hard FK — packages can outlive a renamed technology
            // entry and a soft reference is easier to keep aligned).
            $table->string('language', 60);

            // npm / packagist / pypi / rubygems / cargo / pub / hex /
            // nuget / swiftpm / go / kotlin-jcenter / dotnet-nuget.
            $table->string('registry', 30);

            // active or archived.
            $table->enum('status', ['active', 'archived'])->default('active');

            // ≤ 280 chars, grid card text.
            $table->string('short_description', 280);
            $table->mediumText('long_description')->nullable();
            $table->boolean('long_description_reviewed')->default(false);

            $table->string('repo_url', 500)->nullable();
            $table->string('registry_url', 500)->nullable();
            $table->string('docs_url', 500)->nullable();

            $table->date('shipped_date')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique('slug');
            $table->index('language');
            $table->index('registry');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
