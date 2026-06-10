<?php

use App\Support\BinaryCollation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_learnings', function (Blueprint $table) {
            $table->char('id', 26)->primary()->collation(BinaryCollation::name());
            $table->char('project_id', 26)->collation(BinaryCollation::name());
            $table->string('title', 255);
            // Markdown — what was learned, what to do next time.
            $table->mediumText('description');
            $table->enum('visibility', ['public', 'private']);
            $table->timestamps();

            $table->index(['project_id', 'visibility']);

            $table->foreign('project_id')
                ->references('id')->on('projects')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_learnings');
    }
};
