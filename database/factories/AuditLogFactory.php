<?php

namespace Database\Factories;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AuditLog> */
class AuditLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'actor_id' => null,
            'actor_ip' => fake()->ipv4(),
            'actor_user_agent' => fake()->userAgent(),
            'action' => fake()->randomElement(['create', 'update', 'delete', 'tag_add', 'tag_remove']),
            'subject_type' => 'projects',
            'subject_id' => (string) Str::ulid(),
            'reason' => null,
            'diff' => ['before' => [], 'after' => [], 'affected_pivots' => [], 'truncated' => false],
            'created_at' => now(),
        ];
    }

    /** Merge-capability shape — required reason + before/after canonical_id. */
    public function merge(): static
    {
        return $this->state(function () {
            return [
                'action' => 'merge_capability',
                'subject_type' => 'capabilities',
                'reason' => fake()->sentence(),
                'diff' => [
                    'before' => ['canonical_id' => null],
                    'after' => ['canonical_id' => (string) Str::ulid()],
                    'affected_pivots' => [],
                    'truncated' => false,
                ],
            ];
        });
    }
}
