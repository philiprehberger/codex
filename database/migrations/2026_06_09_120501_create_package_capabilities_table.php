<?php

use App\Support\BinaryCollation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_capabilities', function (Blueprint $table) {
            $table->char('id', 26)->primary()->collation(BinaryCollation::name());
            $table->char('package_id', 26)->collation(BinaryCollation::name());
            $table->char('capability_id', 26)->collation(BinaryCollation::name());
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['package_id', 'capability_id']);
            $table->index('capability_id');
            $table->index(['package_id', 'is_primary']);

            $table->foreign('package_id')
                ->references('id')->on('packages')
                ->cascadeOnDelete();
            $table->foreign('capability_id')
                ->references('id')->on('capabilities')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_capabilities');
    }
};
