<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_tags', function (Blueprint $table) {
            $table->char('id', 26)->primary()->collation('utf8mb4_bin');
            $table->string('name', 120);
            $table->string('slug', 120)->collation('utf8mb4_bin');
            $table->timestamps();

            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_tags');
    }
};
