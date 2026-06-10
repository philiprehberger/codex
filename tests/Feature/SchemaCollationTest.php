<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 7 schema-introspection guard. Every CHAR(26) ULID column and
 * every slug column must report a case-sensitive binary collation —
 * utf8mb4_bin on MySQL, BINARY on sqlite (via App\Support\BinaryCollation).
 *
 * Defends against an accidental ->collation(...) drop in a future
 * migration that would otherwise collapse Crockford-cased ULIDs and
 * accented slug look-alikes to the same value.
 */
class SchemaCollationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_ulid_and_slug_column_is_binary_collated(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite stores collation in sqlite_master; values include
            // "BINARY" for any column declared `COLLATE BINARY`.
            // PRAGMA table_info doesn't expose collation directly; fall
            // back to a SELECT name FROM sqlite_master CREATE TABLE parse.
            $this->assertSqliteBinaryColumns();
            return;
        }

        // MySQL / MariaDB: information_schema.COLUMNS exposes COLLATION_NAME.
        $tables = [
            'projects' => ['id', 'slug'],
            'capabilities' => ['id', 'slug', 'canonical_id'],
            'technologies' => ['id', 'slug'],
            'industries' => ['id', 'slug'],
            'architectures' => ['id', 'slug'],
            'deliverables' => ['id', 'slug'],
            'design_styles' => ['id', 'slug'],
            'project_tags' => ['id', 'slug'],
            'audit_log' => ['id', 'actor_id', 'subject_id'],
        ];

        foreach ($tables as $table => $columns) {
            foreach ($columns as $column) {
                $row = DB::selectOne(
                    'SELECT COLLATION_NAME FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                    [$table, $column]
                );
                $this->assertNotNull($row, "column {$table}.{$column} missing");
                $this->assertSame('utf8mb4_bin', $row->COLLATION_NAME,
                    "{$table}.{$column} must be utf8mb4_bin, got {$row->COLLATION_NAME}");
            }
        }
    }

    private function assertSqliteBinaryColumns(): void
    {
        // sqlite_master.sql contains the original CREATE TABLE statement;
        // we look for `collate 'BINARY'` patterns on each declared column.
        $tables = ['projects', 'capabilities', 'technologies', 'industries',
                   'architectures', 'deliverables', 'design_styles',
                   'project_tags', 'audit_log'];

        foreach ($tables as $table) {
            $row = DB::selectOne("SELECT sql FROM sqlite_master WHERE name = ?", [$table]);
            $this->assertNotNull($row, "table {$table} missing");
            // Every CREATE TABLE for a Codex table should reference BINARY
            // on at least the id column.
            // SQLite stringifies as `collate 'binary'` regardless of declaration
            // case; the semantics are the same as MySQL utf8mb4_bin.
            $this->assertStringContainsString("collate 'binary'", strtolower($row->sql),
                "{$table} CREATE statement must reference 'collate binary'");
        }
    }
}
