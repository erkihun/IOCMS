# IOCMS — Integrated Online Commerce and Marketplace System

A modular-monolith commerce platform implementing the authoritative baselines
**IOCMS-SRS-001** (requirements), **IOCMS-SDD-001** (design) and **IOCMS-LLD-001**
(low-level design) in `docs/`.

Single-vendor commerce at launch, marketplace-ready by design (feature-gated).

## Technology baseline

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 12, PHP 8.4 (`strict_types`), MySQL 8, Redis, Horizon, Scout |
| Frontend | Inertia 2, React 18, TypeScript (strict), Tailwind CSS 4, Vite |
| Search | Laravel Scout (database/Meilisearch adapter — rebuildable projection) |
| Storage | S3-compatible private object storage + CDN (signed URLs) |
| Quality | Pest, Pint, Larastan/PHPStan (level 6), ESLint, tsc, architecture/security tests |

## Architecture

Modular monolith with explicit bounded modules under `app/Domain/*`,
`app/Platform/*`, `app/Experience/Pwa`, cross-cutting primitives in
`app/Support/*`, and a transactional outbox / idempotent inbox. Server-side
authorization (permission + record scope + state + MFA + step-up +
maker-checker + feature gate) on every protected operation. Money is
fixed-precision (no float); public identifiers are UUIDv7; historical commercial
and financial evidence is immutable.

See `docs/implementation/` for the requirement index, business-rule index,
module dependency map, security control matrix, decision register, test strategy
and live progress.

## Prerequisites

- PHP 8.4 with `pdo_mysql`, `redis`, `gd`, `zip`, `bcmath`, `mbstring`, `intl`,
  `sodium` (production also needs `pcntl`/`posix` for Horizon)
- Composer 2, Node 22, npm 11
- MySQL 8 and Redis (local dev may use the bundled SQLite for a quick start)

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
```

Run locally:

```bash
composer run dev          # serve + queue + logs + vite (concurrently)
# or individually:
php artisan serve
php artisan queue:work    # Windows dev; production uses `php artisan horizon`
npm run dev
```

## Quality gates

```bash
composer validate
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
npm run lint
npm run typecheck
npm run build
php artisan route:list
php artisan about
```

The **testing** connection is in-memory SQLite (see
`docs/implementation/decision-register.md` D-002); production/staging/local use
MySQL 8.

## Documentation

- `docs/` — the three authoritative baselines (SRS/SDD/LLD).
- `docs/implementation/` — reading report, requirement/traceability indexes,
  decision register, security matrix, test strategy, progress, final report.
