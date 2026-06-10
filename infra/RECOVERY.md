# Codex Recovery Runbook

A runbook that's never been exercised is a fiction. Each procedure here has a corresponding script under `scripts/deploy/` or `scripts/`, and each has been dry-run on staging at least once.

When you're reading this in an incident: don't panic, take the path that matches the symptom, work through every step.

## Migration failed mid-deploy

**Symptom**: `scripts/deploy/laravel.sh` exits non-zero with "migration failed". The previous release is still serving traffic — the `current` symlink wasn't swapped.

1. SSH into the box:
   ```
   ssh -i ~/.ssh/ps4_new ubuntu@54.190.150.0
   ```
2. Inspect the failed release:
   ```
   cd /var/www/codex/releases/<the-failed-timestamp>
   php artisan migrate:status
   ```
3. Decide:
   - If the failure was an ordering bug, fix the migration locally, commit, redeploy.
   - If the failure left the DB in a partial state, see "Restore from S3 backup" below.
4. The failed release is preserved at `releases/<timestamp>` for inspection. Delete it once you've recovered.

## Rolling back a successful but broken deploy

Use this when the deploy completed cleanly but the app misbehaves in production (HTTP 500s, wrong data, etc).

```bash
./scripts/deploy/recover.sh
```

The script lists releases, picks the previous one, swaps the symlink, gracefuls Apache + reloads PM2. **If the failed deploy ran migrations**, the DB is now ahead of the code — either rollback the migration or restore from backup.

## Restore from S3 backup

S3 bucket: `codex-backups/mysql/`. 30-day retention, STANDARD_IA.

1. Pick the dump:
   ```
   aws s3 ls s3://codex-backups/mysql/ | tail -5
   ```
2. Download to the box:
   ```
   aws s3 cp s3://codex-backups/mysql/codex-YYYY-MM-DD.sql.gz /tmp/
   ```
3. Restore (this DROPS + recreates the live DB):
   ```
   # Stop the queue worker so it can't write during the restore
   pm2 stop codex-queue

   # Drop + recreate the database
   mysql -uroot -p -e "DROP DATABASE codex; CREATE DATABASE codex CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"

   # Restore
   gunzip -c /tmp/codex-YYYY-MM-DD.sql.gz | mysql -uroot -p codex

   # Bring the worker back
   pm2 start codex-queue
   ```
4. Run `php artisan migrate:status` to confirm the restored state matches the code.
5. Smoke-test: hit `/up/diagnostics` and one query endpoint.

**RPO**: 24 hours (worst case = restore yesterday's backup). **RTO**: 1 hour.

PITR is explicitly out-of-scope for Phase 1 — binlog shipping + replay is real operational weight for a single-curator dataset. If buyer signal in Phase 2 funds the work, MySQL binlog → S3 + a `recover-to-timestamp.sh` runbook is the documented next step.

## Rotate revalidate secret

`CODEX_REVALIDATE_SECRETS` is a comma-separated list. The FIRST entry is the active write key; ALL entries are accepted on verify. Rotation is a no-flap two-step:

1. **Generate the new secret**:
   ```
   openssl rand -hex 32
   ```
2. **Append the new secret as the SECOND entry on BOTH hosts** (Laravel-side `shared/.env`, Next.js-side `web/.env`):
   ```
   CODEX_REVALIDATE_SECRETS=current-secret,new-secret
   ```
   Reload PM2 + reload Apache. Both verifiers now accept BOTH secrets. In-flight POSTs from old code still verify.
3. **Once both hosts have rolled** (check `pm2 status` + `systemctl status apache2`), **swap to `new,current` on the writer first**:
   ```
   # Laravel-side shared/.env only
   CODEX_REVALIDATE_SECRETS=new-secret,current-secret
   ```
4. **On the next deploy, drop `current-secret` from the verifier**:
   ```
   # Both shared/.env
   CODEX_REVALIDATE_SECRETS=new-secret
   ```

Verify with a smoke test: trigger a Filament write and confirm the Next.js host returns 204.

## Rotate asset signing key

Identical procedure to `CODEX_REVALIDATE_SECRETS` but with `CODEX_ASSET_SIGNING_KEYS`. This is a SEPARATE secret from `APP_KEY` — rotating asset keys does NOT log users out or break encrypted Eloquent columns.

The two-hour signed-URL TTL means in-flight URLs minted before the rotation continue to work for up to 2 hours after the verifier drops the old key. If you need to invalidate ALL outstanding URLs immediately (compromise response), drop the previous key in step 4 as soon as step 2's deploy completes.

## Rotate admin credentials

If the admin password is compromised:

```bash
ssh ubuntu@54.190.150.0
cd /var/www/codex/current
php artisan codex:reset-admin-password --email=admin@philiprehberger.com
# Enter the new password (min 16, mixed case, numbers, symbols, not pwned)
```

The reset is logged to `audit_log` with `action=admin_password_reset`. Notify any other operators.

## Last-resort 2FA reset

If 1Password AND the sealed paper recovery codes are both unrecoverable:

```bash
ssh ubuntu@54.190.150.0
cd /var/www/codex/current
php artisan codex:reset-2fa --email=admin@philiprehberger.com --confirm
```

This clears `app_authentication_secret` + `app_authentication_recovery_codes`. The admin will be forced to re-enroll at next `/admin` login. The reset is logged to `audit_log` with `action=reset_2fa`.

## Edge cases

- **Apache won't reload after a vhost change**: run `sudo apachectl configtest` and read the error. Most common: a typo in a `Header` directive or a missing `Include` file.
- **PM2 says "process not found"** for `codex-web` or `codex-queue`: `pm2 start /var/www/ecosystem.config.js --only codex-web` (or `--only codex-queue`).
- **Certbot fails on renewal**: check the CAA record on the apex (`dig CAA philiprehberger.com`) — Let's Encrypt must be in the allow-list.
- **`/up/queue` is 503 but PM2 says the worker is up**: the worker hasn't written a heartbeat. Check the worker's stdout (`pm2 logs codex-queue`) for runtime errors.
- **Filament write reports success but the Next.js dashboard hasn't updated**: check the worker's failed_jobs table (`SELECT * FROM failed_jobs ORDER BY id DESC LIMIT 5;`). If a `RevalidateCacheJob` is failing, the cache invalidation queue is stalled.

## Quarterly restore drill

Calendar reminder: every quarter, restore the latest S3 backup to a scratch DB and run a smoke query. Documented in `scripts/test-restore.sh`. The CI runs a weekly drill against a synthetic dump; the quarterly drill is the real-data exercise.
