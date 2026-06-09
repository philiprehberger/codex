# Codex

> The codex of every project, capability, and gap.

- **Dashboard:** [codex.philiprehberger.com](https://codex.philiprehberger.com) *(coming soon)*
- **API + admin:** [api.codex.philiprehberger.com](https://api.codex.philiprehberger.com) *(coming soon)*
- **Stack:** Laravel 13 + Filament v4 + MySQL 8 + Next.js 16 + Tailwind 4
- **Plan:** `~/projects/income-ops/.scratch/plans/project_intelligence_codex_portfolio.md`

A portfolio intelligence dashboard. Every project Philip has built — demos, packages, client engagements — catalogued, tagged along multiple axes (capability, technology, industry, architecture, deliverable), and rendered as a public capability heatmap + gap report.

The piece is meta: it's both a portfolio entry (Laravel + Filament + Next.js analytics dashboard) and the source-of-truth for what shows up on every other sales surface — resume bullets, Fiverr gigs, Upwork screening answers.

## Repo layout

```
codex/
├── app/                 Laravel 13 application (API + Filament admin)
├── web/                 Next.js 16 dashboard (codex.philiprehberger.com)
├── database/
│   ├── migrations/      v1+v2 schema — ~15 tables, ULID + slug, utf8mb4_bin
│   ├── seeders/         portfolio dogfood — real projects, real tags
│   └── fixtures/        portfolio-bundle excerpts (CI-safe seed input)
├── infra/
│   ├── apache/          per-host vhosts (codex.*, api.codex.*)
│   └── cron/            assert-invariants, audit-slug-collisions, export-portfolio
├── scripts/deploy/      atomic-release deploy (Laravel + Next.js)
└── docs/                architecture, api-conventions, invariants, upgrades
```

## Local development

### Prerequisites

- PHP 8.3+, Composer 2.9+, Node 22+/npm 10+, MySQL 8

### Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
# Create MySQL `codex_dev` schema, then:
php artisan migrate:fresh --seed
composer dev
```

Filament admin → http://localhost:8000/admin
Laravel health → http://localhost:8000/up

## Deployment

Atomic-release deploy to EC2. See `scripts/deploy/deploy.cjs` and `.env.deployment.example`.

```bash
cp .env.deployment.example .env.deployment
npm run deploy
```

## License

MIT
