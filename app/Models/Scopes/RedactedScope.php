<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Strips client_name and internal_notes from any project row where
 * visibility=redacted. Applied as a global scope on Project; Filament
 * admin opts out via Project::withoutGlobalScope(RedactedScope::class).
 *
 * Implementation: rewrites the column list to project NULL for the two
 * redacted columns when visibility='redacted'. Done at query time so
 * downstream code (controllers, API resources, Tinker) sees nulls
 * uniformly without per-call awareness. $hidden is the serialiser-level
 * defence; this scope is the row-level one.
 */
class RedactedScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // If a query already targets specific columns, leave it alone —
        // it knows what it wants. We only intervene on the default '*'
        // select that controllers and API resources use.
        $existing = $builder->getQuery()->columns;
        if ($existing !== null && $existing !== ['*']) {
            return;
        }

        $table = $model->getTable();
        $columns = $model->getConnection()->getSchemaBuilder()->getColumnListing($table);

        $selects = [];
        $bindings = [];
        foreach ($columns as $column) {
            if (in_array($column, ['client_name', 'internal_notes'], true)) {
                $selects[] = "CASE WHEN `{$table}`.`visibility` = ? THEN NULL ELSE `{$table}`.`{$column}` END AS `{$column}`";
                $bindings[] = 'redacted';
            } else {
                $selects[] = "`{$table}`.`{$column}`";
            }
        }

        $builder->selectRaw(implode(', ', $selects), $bindings);
    }
}
