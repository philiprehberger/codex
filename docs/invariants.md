# Codex invariants

The cross-cutting rules. Each invariant lists every enforcement point so changing the rule = touching every site in one PR. The CI `invariants-doc` job (Phase 7) gates PRs touching `app/Models/`, `app/Actions/`, `app/Observers/`, or `app/Services/Merge*.php` against changes to this file.

If you can't find an invariant here that should be, **add it** — the doc is the single source of truth.

## Project lifecycle

### Status transition matrix

| from \ to   | idea | active | shipped | archived |
|-------------|:----:|:------:|:-------:|:--------:|
| idea        |  —   |   ✓    |    ✓†   |     ✓    |
| active      |  ✓‡  |   —    |    ✓†   |     ✓    |
| shipped     |  ✗   |   ✓‡   |    —    |     ✓    |
| archived    |  ✗   |   ✓‡   |    ✓‡   |     —    |

- `✓` allowed.
- `✓†` requires `shipped_date` + `hours_actual` non-null (validation + observer).
- `✓‡` audit-logged with a required `reason` (backwards transition).
- `✗` forbidden.

**Enforced by**:
- `Project::booted()` `saving` observer (throws ValidationException on `shipped` without the required fields)
- ProjectResource form's `requiredIf` on `shipped_date` + `hours_actual`
- `tests/Feature/ShippedInvariantTest`

## RedactedScope — model-layer boundary

`Project::booted()` registers `App\Models\Scopes\RedactedScope` as a global scope. The scope's `apply()` rewrites the SELECT via `selectRaw` with `CASE WHEN visibility='redacted' THEN NULL ELSE col END` for `client_name` and `internal_notes`.

**Enforced by**:
- Global scope on `Project` (default for all queries unless `withoutGlobalScope` is called)
- `Project::$hidden = ['internal_notes']` (serialiser-level second layer)
- `phpstan-laravel` rule (manual review for now — Phase 7 wires the automated check) banning `DB::raw` / `DB::select` outside `database/migrations` and `app/Console/Commands`
- `tests/Feature/RedactedScopeTest` (4 cases)

Filament admin opts out: `Project::withoutGlobalScope(RedactedScope::class)` — covered by `ProjectResource::getEloquentQuery()`.

## ULID + slug discipline

- Every internal id is a CHAR(26) ULID. Public-facing routes use `slug` for SEO + memorability.
- Every ULID + slug column ships `COLLATE utf8mb4_bin` on MySQL (`BINARY` on sqlite via `App\Support\BinaryCollation::name()`). Without this, MySQL's default `utf8mb4_0900_ai_ci` is case-insensitive AND accent-insensitive, and Crockford-cased ULIDs collide with anything lowercased for URL display.

**Enforced by**:
- `BinaryCollation::name()` called in every migration's column declarations
- `tests/Feature/SchemaCollationTest` (asserts every ULID + slug column reports the right collation)

## SlugRule — kebab-case + reserved-word reject

Defined at `app/Rules/SlugRule.php`. Rejects:
- non-string, empty
- < 3 chars or > 120 chars
- non-kebab-case (`[a-z0-9-]+`, no leading/trailing/double hyphen)
- reserved first-segments (live route table via `Route::getRoutes()` + `FALLBACK_RESERVED` list)

**Enforced by**:
- Project resource form's `slug` input
- API filter `?capability=…` etc — validation passes through this shape via FormRequest rules
- Console seeders that use `updateOrCreate(['slug' => …])`
- `codex:audit-slug-collisions` nightly cron (catches drift after a route added in a later phase shadows an existing slug)
- `tests/Feature/SlugRuleTest` (8 cases)

## Capability vocabulary moderation

- Capabilities are NEVER deleted via the admin UI — `CapabilityResource::toolbarActions([])` removes the delete action; the pivot table's `ON DELETE RESTRICT` on `capability_id` is the schema-level floor.
- The only removal path is **merge** via `App\Actions\MergeCapability`.

Merge rules:

1. **Self-merge rejected**: `canBeMergedInto` rejects `$source->id === $target->id`.
2. **Cycle rejected**: `canBeMergedInto` rejects if `$target->resolveCanonical()->id === $source->id`.
3. **Alias-target allowed (rewritten to terminal)**: the plan's "Aliases can't be merge targets" rule is reinterpreted as a UX cue (picker hides aliases) — when the action receives an alias target, it walks `resolveCanonical()` and writes the terminal canonical to `$source->canonical_id`. Documented as a plan deviation in the project memory.
4. **Reason required** (≤ 255 chars). Empty / whitespace reasons → ValidationException.
5. **Always rewrite to terminal canonical**. Read-side resolution is always one hop.
6. **Unmerge is local**, not chain-wide. Unmerging the `A → B` row in an `A → B → C` chain restores only A's prior `canonical_id`; B → C stays intact.

**Enforced by**:
- `App\Actions\MergeCapability` (the one supported write path)
- `Capability::canBeMergedInto()` + `resolveCanonical()`
- `CapabilityResource` merge action UI + ConfirmRequiresReason validation
- `AuditLogResource` unmerge action (restores `diff.before.canonical_id`)
- `tests/Feature/CapabilityMergeTest` (5 cases)

## Vocabulary caps

| dimension       | warn at | hard cap |
|-----------------|:-------:|:--------:|
| capabilities    |   60    |    80    |
| technologies    |   80    |   120    |
| industries      |   20    |    30    |
| architectures   |   10    |    20    |
| deliverables    |   10    |    20    |
| design_styles   |   10    |    20    |
| project_tags    |   40    |    80    |

**Enforced by**:
- `config/codex.php → vocabulary.<dimension>` (the warn + cap values)
- Every `*Seeder` reads its dimension cap and throws on overflow
- `CapabilityResource::modifyQueryUsing` emits a danger notification at `cap`, warning at `warn`
- `codex:assert-invariants` cron (vocabulary-cap check is a sub-step)

## One primary per dimension

At most one `project_capabilities.is_primary = 1` per project (same for `project_technologies`).

**Enforced by**:
- `App\Actions\SetPrimaryTag` — transactional with `lockForUpdate()`, clears any existing primary on the dimension before setting the new one
- `Project::saving` observer (re-asserts on direct pivot writes from CLI / tinker / seeders that bypass the action)
- `codex:assert-invariants` nightly cron (catches drift)

## Soft-delete + cascade discipline

Pivot tables declare `ON DELETE CASCADE` on `project_id`. The cascade DOES NOT FIRE on soft-delete (Laravel sets `deleted_at` rather than deleting the row).

This is correct for the restore path (un-soft-deleting brings tags back) but breaks every heatmap / gap / count aggregation that joins through `project_capabilities` directly: the soft-deleted project's tags still inflate counts.

**Read-side discipline**: every read-side query that aggregates a pivot MUST either:
1. Join through `projects` and filter `WHERE projects.deleted_at IS NULL`, OR
2. Use the `Project` model's relations (which respect `SoftDeletes` automatically)

Raw `DB::table('project_capabilities')->count()` is **forbidden** outside migrations + console commands.

**Enforced by**:
- The `phpstan-laravel` rule (manual review for now; automated check on the Phase 7 roadmap)
- Every controller's queries (verified via review at PR time)
- A planned Phase 7 PHPUnit test exercising the soft-delete → restore round-trip

## MySQL `sql_mode` + isolation

`config/database.php` pins per-connection:

- `sql_mode`: `STRICT_TRANS_TABLES, NO_ZERO_DATE, NO_ZERO_IN_DATE, ERROR_FOR_DIVISION_BY_ZERO, NO_ENGINE_SUBSTITUTION`
- Transaction isolation: `READ COMMITTED` (via `MYSQL_ATTR_INIT_COMMAND`)
- Default collation: `utf8mb4_0900_ai_ci` (ULID + slug columns override to `utf8mb4_bin` per-column)

**Why pinned**: REPEATABLE READ (MySQL default) takes gap locks under `SELECT … FOR UPDATE` that block concurrent inserts to unrelated pivot rows. The `SetPrimaryTag` action depends on READ COMMITTED to lock the project row without poisoning unrelated rows.

## HMAC byte-stability

Both HMAC surfaces use a BYTE-EXACT canonical body:

- Revalidate POST: `json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)` + `Http::withBody(...)` (never `Http::post([...])`)
- Asset signing: `"{ulid}|{exp}"` (pipe-separated)

The verifier reads the RAW body before `JSON.parse` (or before any sanity-check parsing on the Laravel side).

**Enforced by**:
- `App\Services\RevalidateClient` (Laravel-side write)
- `web/app/api/revalidate/route.ts` (Next.js-side verify via `req.text()` before `JSON.parse`)
- `App\Services\AssetSigner` (sign + verify)
- `tests/Feature/RevalidateClientSignatureTest`
- `tests/Feature/AssetSignerTest`
- `web/tests/revalidate-route.test.ts`

## No-flap rotation

Both `CODEX_REVALIDATE_SECRETS` and `CODEX_ASSET_SIGNING_KEYS` are COMMA-SEPARATED LISTS. The FIRST entry is the active write key; ALL entries are accepted on verify.

Rotation procedure: see `infra/RECOVERY.md` → "Rotate revalidate secret" and "Rotate asset signing key".

**Enforced by**:
- `RevalidateClient::secrets()` (writer reads first; verifier on Next.js side tries all)
- `AssetSigner::keys()` (writer reads first; verifier tries all)
- `tests/Feature/AssetSignerTest` no-flap rotation test
- `tests/Feature/RevalidateClientSignatureTest`
