<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Returns the right case-sensitive binary collation name for the current
 * connection's driver. Used in migrations on every ULID + slug column.
 *
 * MySQL 8: 'utf8mb4_bin' — Crockford ULIDs and accented slug look-alikes
 *   compare as distinct values (the plan's load-bearing requirement).
 * SQLite: 'BINARY' — SQLite's built-in byte-comparison collation,
 *   semantically identical to utf8mb4_bin for the column shapes Codex
 *   uses (lowercase ULIDs + kebab-case slugs).
 *
 * Centralises the driver branch so the migrations stay flat. The Phase 7
 * schema-introspection test re-asserts utf8mb4_bin in MySQL specifically.
 */
final class BinaryCollation
{
    public static function name(?string $driver = null): string
    {
        $driver ??= DB::connection()->getDriverName();

        return match ($driver) {
            'mysql', 'mariadb' => 'utf8mb4_bin',
            'sqlite' => 'BINARY',
            default => 'utf8mb4_bin',
        };
    }
}
