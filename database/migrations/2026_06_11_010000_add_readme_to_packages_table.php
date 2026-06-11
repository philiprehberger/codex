<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cache GitHub READMEs in the packages table.
 *
 * `readme_markdown` holds the raw markdown source as fetched from GitHub
 * (decoded from the API's base64). The dashboard renders it via
 * react-markdown + remark-gfm on the package detail page. LONGTEXT
 * because some READMEs are large (~50KB+) and the existing mediumText
 * fits 16MB but LONGTEXT gives headroom without performance cost.
 *
 * `readme_fetched_at` lets the `codex:fetch-package-readmes` command
 * skip recently-refreshed packages on incremental runs (default 7-day
 * window). Pass --force to refresh everything.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->longText('readme_markdown')->nullable()->after('long_description_reviewed');
            $table->timestamp('readme_fetched_at')->nullable()->after('readme_markdown');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['readme_markdown', 'readme_fetched_at']);
        });
    }
};
