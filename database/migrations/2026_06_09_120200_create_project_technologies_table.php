<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_technologies', function (Blueprint $table) {
            $table->char('id', 26)->primary()->collation('utf8mb4_bin');
            $table->char('project_id', 26)->collation('utf8mb4_bin');
            $table->char('technology_id', 26)->collation('utf8mb4_bin');
            // At most one primary per project per dimension — invariant
            // enforced by SetPrimaryTag action + saved observer + nightly
            // codex:assert-invariants. MySQL 8 lacks partial unique support.
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['project_id', 'technology_id']);
            $table->index('technology_id');
            $table->index(['project_id', 'is_primary']);

            $table->foreign('project_id')
                ->references('id')->on('projects')
                ->cascadeOnDelete();
            $table->foreign('technology_id')
                ->references('id')->on('technologies')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_technologies');
    }
};
