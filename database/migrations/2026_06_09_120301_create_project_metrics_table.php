<?php

use App\Support\BinaryCollation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_metrics', function (Blueprint $table) {
            $table->char('id', 26)->primary()->collation(BinaryCollation::name());
            $table->char('project_id', 26)->collation(BinaryCollation::name());
            // Temporal: snapshot date. Project::latestMetrics() returns the
            // most recent row; resume-bullet aggregations always use the
            // latest. Seeder writes one Day-0 row; Filament has a "snapshot
            // metrics" action that captures current values as a new row.
            $table->date('recorded_at');

            $table->integer('duration_days')->nullable();
            $table->integer('api_integrations')->nullable();
            $table->integer('database_tables')->nullable();
            $table->integer('test_count')->nullable();
            $table->unsignedTinyInteger('lighthouse_perf')->nullable();
            $table->unsignedTinyInteger('lighthouse_a11y')->nullable();
            $table->unsignedTinyInteger('lighthouse_best')->nullable();
            $table->unsignedTinyInteger('lighthouse_seo')->nullable();
            $table->integer('loc_total')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['project_id', 'recorded_at']);
            $table->index(['project_id', 'recorded_at']);

            $table->foreign('project_id')
                ->references('id')->on('projects')
                ->cascadeOnDelete();
        });

        // CHECK constraints (MySQL 8.0.16+) — defence-in-depth against
        // Tinker writes and any future ingestion path that bypasses
        // Filament's form validators. App-layer duplicates these for UX;
        // the schema is the floor.
        //
        // Skipped on sqlite — used for in-memory test runs only. The Phase 7
        // PHPUnit run against MySQL will assert the constraints exist.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE project_metrics
                ADD CONSTRAINT chk_pm_lighthouse_perf CHECK (lighthouse_perf IS NULL OR (lighthouse_perf BETWEEN 0 AND 100)),
                ADD CONSTRAINT chk_pm_lighthouse_a11y CHECK (lighthouse_a11y IS NULL OR (lighthouse_a11y BETWEEN 0 AND 100)),
                ADD CONSTRAINT chk_pm_lighthouse_best CHECK (lighthouse_best IS NULL OR (lighthouse_best BETWEEN 0 AND 100)),
                ADD CONSTRAINT chk_pm_lighthouse_seo  CHECK (lighthouse_seo  IS NULL OR (lighthouse_seo  BETWEEN 0 AND 100)),
                ADD CONSTRAINT chk_pm_duration_days   CHECK (duration_days   IS NULL OR duration_days    >= 0),
                ADD CONSTRAINT chk_pm_api_integ       CHECK (api_integrations IS NULL OR api_integrations >= 0),
                ADD CONSTRAINT chk_pm_database_tables CHECK (database_tables IS NULL OR database_tables  >= 0),
                ADD CONSTRAINT chk_pm_test_count      CHECK (test_count      IS NULL OR test_count       >= 0),
                ADD CONSTRAINT chk_pm_loc_total       CHECK (loc_total       IS NULL OR loc_total        >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_metrics');
    }
};
