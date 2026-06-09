<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('industries', function (Blueprint $table) {
            $table->char('id', 26)->primary()->collation(\App\Support\BinaryCollation::name());
            $table->string('name', 120);
            $table->string('slug', 120)->collation(\App\Support\BinaryCollation::name());
            $table->timestamps();

            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('industries');
    }
};
