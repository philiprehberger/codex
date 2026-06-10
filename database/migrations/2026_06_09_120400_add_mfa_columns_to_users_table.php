<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Filament v5's first-party Multi-Factor Auth (per the Phase 3 Day-1 spike
 * — Filament v5.6 ships TOTP "App" provider + recovery codes + forced
 * enrolment middleware). The InteractsWithAppAuthentication concern auto-
 * casts these as encrypted; mergeHidden hides them from serialisation.
 *
 * The plan named these `two_factor_secret` + `two_factor_recovery_codes`
 * (matching laragear/two-factor which was the documented fallback) — the
 * spike picked Filament's first-party path so the column names follow
 * Filament's contract instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('app_authentication_secret')->nullable()->after('password');
            $table->text('app_authentication_recovery_codes')->nullable()->after('app_authentication_secret');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['app_authentication_secret', 'app_authentication_recovery_codes']);
        });
    }
};
