<?php

use App\Support\BinaryCollation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_deliverables', function (Blueprint $table) {
            $table->char('id', 26)->primary()->collation(BinaryCollation::name());
            $table->char('project_id', 26)->collation(BinaryCollation::name());
            $table->char('deliverable_id', 26)->collation(BinaryCollation::name());
            $table->timestamps();

            $table->unique(['project_id', 'deliverable_id']);
            $table->index('deliverable_id');

            $table->foreign('project_id')
                ->references('id')->on('projects')
                ->cascadeOnDelete();
            $table->foreign('deliverable_id')
                ->references('id')->on('deliverables')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_deliverables');
    }
};
