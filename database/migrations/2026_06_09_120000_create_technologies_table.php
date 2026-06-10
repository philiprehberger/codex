<?php

use App\Support\BinaryCollation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technologies', function (Blueprint $table) {
            $table->char('id', 26)->primary()->collation(BinaryCollation::name());
            $table->string('name', 120);
            $table->string('slug', 120)->collation(BinaryCollation::name());
            // language, framework, cms, database, infrastructure, cloud, tooling, api, library
            $table->string('category', 60);
            $table->string('icon_url', 500)->nullable();
            $table->string('vendor_url', 500)->nullable();
            $table->timestamps();

            $table->unique('slug');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technologies');
    }
};
