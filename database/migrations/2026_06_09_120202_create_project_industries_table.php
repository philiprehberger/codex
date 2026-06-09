<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_industries', function (Blueprint $table) {
            $table->char('id', 26)->primary()->collation('utf8mb4_bin');
            $table->char('project_id', 26)->collation('utf8mb4_bin');
            $table->char('industry_id', 26)->collation('utf8mb4_bin');
            $table->timestamps();

            $table->unique(['project_id', 'industry_id']);
            $table->index('industry_id');

            $table->foreign('project_id')
                ->references('id')->on('projects')
                ->cascadeOnDelete();
            $table->foreign('industry_id')
                ->references('id')->on('industries')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_industries');
    }
};
