<?php

use App\Support\BinaryCollation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_technologies', function (Blueprint $table) {
            $table->char('id', 26)->primary()->collation(BinaryCollation::name());
            $table->char('package_id', 26)->collation(BinaryCollation::name());
            $table->char('technology_id', 26)->collation(BinaryCollation::name());
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->foreign('package_id')->references('id')->on('packages')->cascadeOnDelete();
            $table->foreign('technology_id')->references('id')->on('technologies')->cascadeOnDelete();

            $table->unique(['package_id', 'technology_id'], 'pkg_tech_unique');
            $table->index('technology_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_technologies');
    }
};
