<?php

use App\Support\BinaryCollation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The _map suffix avoids the name collision with the project_tags
        // lookup table (load-bearing — not drift).
        Schema::create('project_tags_map', function (Blueprint $table) {
            $table->char('id', 26)->primary()->collation(BinaryCollation::name());
            $table->char('project_id', 26)->collation(BinaryCollation::name());
            $table->char('project_tag_id', 26)->collation(BinaryCollation::name());
            $table->timestamps();

            $table->unique(['project_id', 'project_tag_id']);
            $table->index('project_tag_id');

            $table->foreign('project_id')
                ->references('id')->on('projects')
                ->cascadeOnDelete();
            $table->foreign('project_tag_id')
                ->references('id')->on('project_tags')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_tags_map');
    }
};
