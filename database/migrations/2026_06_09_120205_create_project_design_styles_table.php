<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_design_styles', function (Blueprint $table) {
            $table->char('id', 26)->primary()->collation(\App\Support\BinaryCollation::name());
            $table->char('project_id', 26)->collation(\App\Support\BinaryCollation::name());
            $table->char('design_style_id', 26)->collation(\App\Support\BinaryCollation::name());
            $table->timestamps();

            $table->unique(['project_id', 'design_style_id']);
            $table->index('design_style_id');

            $table->foreign('project_id')
                ->references('id')->on('projects')
                ->cascadeOnDelete();
            $table->foreign('design_style_id')
                ->references('id')->on('design_styles')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_design_styles');
    }
};
