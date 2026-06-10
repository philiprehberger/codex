# Phase 8 — User-action close-out

The local artifacts are committed. **The steps below need to be run by you** on the EC2 box, your DNS provider, and the third-party SaaS accounts. I can't do these — they require sudo, SSH credentials, account ownership, or browser interaction.

Surface them as you work through Phase 8 deploy.

## 1. DNS — A records + CAA

Add to your DNS provider:

```
codex.philiprehberger.com.       A    54.190.150.0
api.codex.philiprehberger.com.   A    54.190.150.0
```

If a CAA record exists on `philiprehberger.com.`, confirm it includes Let's Encrypt:

```
philiprehberger.com.   CAA  0 issue "letsencrypt.org"
philiprehberger.com.   CAA  0 issuewild "letsencrypt.org"
```

Verify with:

```bash
dig +short codex.philiprehberger.com
dig +short api.codex.philiprehberger.com
dig CAA philiprehberger.com +short
```

`scripts/deploy/preflight.sh` will fail until these resolve.

## 2. Server directories + secrets

SSH into the box and create:

```bash
sudo mkdir -p /var/www/codex/{releases,shared/storage/{app/{private,public}/projects,framework/{cache,sessions,views},logs,exports}}
sudo mkdir -p /var/www/codex-web
sudo mkdir -p /backups/codex
sudo chown -R ubuntu:ubuntu /var/www/codex /var/www/codex-web /backups/codex
```

Write `/var/www/codex/shared/.env`. Mode **0640, root:www-data** (or whichever group Apache runs as) — never world-readable. Contents:

```
APP_NAME=Codex
APP_ENV=production
APP_KEY=base64:...                     # php artisan key:generate
APP_DEBUG=false
APP_URL=https://api.codex.philiprehberger.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=codex
DB_USERNAME=codex
DB_PASSWORD=<from openssl rand -hex 24>

CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database

CODEX_API_INTERNAL_URL=http://127.0.0.1
CODEX_PUBLIC_API_URL=https://api.codex.philiprehberger.com

# 32-byte hex (openssl rand -hex 32) — comma-separated for no-flap rotation
CODEX_REVALIDATE_SECRETS=<active-key>
CODEX_ASSET_SIGNING_KEYS=<active-key>
CODEX_NEXT_REVALIDATE_URL=http://127.0.0.1:3012

SENTRY_LARAVEL_DSN=https://...@...sentry.io/...
```

Confirm:

```bash
stat -c '%a %U:%G' /var/www/codex/shared/.env
# expect: 640 root:www-data
```

Write `/var/www/codex-web/.env` (Next.js):

```
PORT=3012
NODE_ENV=production
CODEX_API_INTERNAL_URL=http://127.0.0.1
NEXT_PUBLIC_CODEX_API_HOST=api.codex.philiprehberger.com
CODEX_REVALIDATE_SECRETS=<same value as Laravel-side>
```

## 3. MySQL — DB + user

```bash
sudo mysql -e "CREATE DATABASE codex CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE USER 'codex'@'localhost' IDENTIFIED BY '<DB_PASSWORD from shared/.env>';
GRANT ALL ON codex.* TO 'codex'@'localhost';
FLUSH PRIVILEGES;"
```

## 4. Apache vhosts

```bash
cd /var/www/codex/current/infra/apache
sudo cp codex.philiprehberger.com.conf api.codex.philiprehberger.com.conf /etc/apache2/sites-available/
sudo a2ensite codex.philiprehberger.com.conf api.codex.philiprehberger.com.conf
sudo apachectl configtest
sudo apachectl graceful
```

## 5. PM2 + ecosystem.config.js

Paste the `apps[]` entries from `infra/pm2/codex-ecosystem.snippet.js` into `/var/www/ecosystem.config.js`. Then:

```bash
pm2 start /var/www/ecosystem.config.js --only codex-web,codex-queue
pm2 save
pm2 startup   # if not already configured
```

## 6. Certbot (Let's Encrypt)

```bash
sudo certbot --apache -d codex.philiprehberger.com -d api.codex.philiprehberger.com
```

After certbot finishes, **edit `/etc/apache2/sites-available/api.codex.philiprehberger.com-le-ssl.conf`** to add the loopback http→https redirect carve-out:

```apache
# Inside the rewrite block certbot wrote, add:
RewriteCond %{REMOTE_ADDR} !=127.0.0.1
```

This stops Next.js loopback fetches from being redirected into TLS. Verify:

```bash
curl -I -H 'Host: api.codex.philiprehberger.com' http://127.0.0.1/up
# expect: HTTP/1.1 200 OK (NOT 301)
```

## 7. BetterStack monitors

Create monitors on [BetterStack](https://betterstack.com) (free tier, 10 monitors). The cron file `infra/cron/codex-crontab` has placeholder `REPLACE_ME_*` heartbeat URLs — swap them for real IDs.

Recommended monitors:
- `https://codex.philiprehberger.com/` — every 60s
- `https://codex.philiprehberger.com/heatmap` — every 60s
- `https://api.codex.philiprehberger.com/up` — every 60s
- `https://api.codex.philiprehberger.com/up/diagnostics` — every 60s
- `https://api.codex.philiprehberger.com/up/queue` — every 60s
- Heartbeats for every cron job in `infra/cron/codex-crontab`

Notification channel: email + Slack incoming-webhook `#codex-alerts`.

## 8. Sentry project

Create a Sentry project for Codex. Copy the DSN into `/var/www/codex/shared/.env` as `SENTRY_LARAVEL_DSN`. Inbound rate filter: cap any single fingerprint at 100 events/hour to protect the 5k/mo budget.

## 9. S3 bucket

```bash
aws s3 mb s3://codex-backups
aws s3api put-bucket-versioning --bucket codex-backups \
    --versioning-configuration Status=Enabled
aws s3api put-bucket-lifecycle-configuration --bucket codex-backups \
    --lifecycle-configuration file://(see runbook for the JSON)
```

Lifecycle rule: transition objects to STANDARD_IA after 1 day; delete `mysql/` objects after 30 days, `access-logs/` after 90 days.

## 10. Cron install

```bash
sudo crontab -u ubuntu -e
# paste contents of infra/cron/codex-crontab
# update REPLACE_ME_* placeholder heartbeat URLs from step 7
```

## 11. First deploy

From your local machine:

```bash
./scripts/deploy/all.sh --target=production --first-deploy
```

The `--first-deploy` flag sets `CODEX_ALLOW_SEEDERS_IN_PRODUCTION=true` for the single `migrate:fresh --seed` run.

## 12. Smoke checklist

After first deploy, walk this checklist:

- `curl -I https://codex.philiprehberger.com` → 200, security headers present (HSTS + CSP + frame-options + content-type-options)
- `curl -I https://api.codex.philiprehberger.com/up` → 200 empty body
- `curl https://api.codex.philiprehberger.com/up/diagnostics` → `{"status":"ok","db":"ok",…}`
- `curl https://api.codex.philiprehberger.com/up/queue` → 200 (after the codex-queue worker writes its first heartbeat)
- `curl -H 'Host: api.codex.philiprehberger.com' http://127.0.0.1/up` (from the EC2 box) → 200, not 301
- `curl -I https://codex.philiprehberger.com/_next/static/chunks/main.js.map` → 404 (source maps not public)
- `curl https://codex.philiprehberger.com/robots.txt` → contains `Disallow: /admin`, `Disallow: /api/`, `Disallow: /api/v1/assets/`
- `https://api.codex.philiprehberger.com/admin/login` → 200 with login form
- Log in with the admin credentials → 2FA enrolment flow appears (Filament `requiresMultiFactorAuthentication`)
- Heatmap visible at `https://codex.philiprehberger.com/heatmap`

## 13. Seed admin user

After first deploy, but BEFORE you log in via the panel:

```bash
ssh ubuntu@54.190.150.0
cd /var/www/codex/current
php artisan codex:seed-admin --email=admin@philiprehberger.com
# enter password (min 16, mixed case, numbers, symbols, not pwned)
```

2FA enrolment is forced on first `/admin` login. Save the QR code + recovery codes in 1Password AND seal a paper copy in a fireproof safe — two independent recovery vectors (per the plan's last-resort rule).

## 14. Restore drill — manual exercise

Once everything is live, manually run the restore drill:

```bash
ssh ubuntu@54.190.150.0
cd /var/www/codex/current
./scripts/test-restore.sh
```

Confirm it round-trips clean. The CI runs a weekly synthetic drill; the quarterly real-data drill is your calendar reminder.

---

If any step fails, `infra/RECOVERY.md` is the troubleshooting reference.
