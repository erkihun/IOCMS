# IOCMS — Deployment & Operations Runbook

Baseline operational guidance per IOCMS-SDD §13, IOCMS-LLD §10 and prompt §21/§26.
Production values (hosts, credentials) are **secret-manager references**, never in
source control (NFR-SE-016).

## 1. Environments

| Env | DB | Cache/Queue/Session | Search | Storage | Debug |
|-----|----|--------------------|--------|---------|-------|
| local | MySQL 8 (or bundled SQLite) | Redis | db/meilisearch | local disk | on |
| testing | in-memory SQLite | array/sync | collection | local | on |
| staging | MySQL 8 | Redis | Meilisearch | S3 | off |
| production | MySQL 8 (PITR) | Redis | Meilisearch | S3 + CDN | off |

Env examples: `.env.example` (local), `environments/.env.{testing,staging,production}.example`.

## 2. Install / first deploy

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env            # then set secret-manager references
php artisan key:generate
php artisan migrate --force      # never migrate:fresh in production
php artisan db:seed --class=RolePermissionSeeder --force
php artisan db:seed --class=LocalizationSeeder --force
npm ci && npm run build
php artisan config:cache route:cache view:cache
```

The `SuperAdminSeeder` and `CatalogSeeder` are **development-only** (they no-op /
must not run in production). Create the initial admin through a controlled,
audited provisioning step instead.

## 3. Queues (Horizon)

Named queues (LLD §3.6, prompt §13): `critical-commerce`, `payments`, `inventory`,
`notifications`, `imports-exports`, `search-projection`, `reporting`,
`media-processing`, `maintenance`, plus `transactional-outbox` and `default`.

```bash
php artisan horizon           # supervised workers (Linux; needs ext-pcntl/posix)
php artisan horizon:terminate # graceful restart on deploy (after code swap)
```

Windows dev fallback: `php artisan queue:work`.

## 4. Scheduler

Register the single cron entry that drives Laravel's scheduler:

```
* * * * * cd /path/to/iocms && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled tasks use `withoutOverlapping()` / `onOneServer()` and persist execution
evidence to `scheduled_task_runs` (FR-OPS-008). Key jobs: reservation-expiry
release, outbox dispatch, payment reconciliation, projection rebuild, backup,
restore-verification.

## 5. Deploy (zero-downtime, rollback-safe)

1. Build immutable artifact; record source commit + dependency lock hashes.
2. Run `composer validate`, tests, Pint, PHPStan, npm lint/typecheck/build in CI —
   deploy is gated on all passing (`.github/workflows/ci.yml`).
3. Apply **expand** migrations (backward-compatible) before the code swap; defer
   **contract** migrations to a later release (LLD §15.4 expand/migrate/contract).
4. Swap code; `php artisan migrate --force`; `config:cache route:cache view:cache`;
   `horizon:terminate`.
5. Health check `/up` and the operational health endpoint; roll back the artifact
   (and, if needed, the last reversible migration) on failure.

Destructive production migrations are never run automatically (prompt §21).

## 6. Backup & recovery (RPO ≤ 15 min, RTO ≤ 4 h)

- Encrypted database backups + binlog for point-in-time recovery; encrypted object
  storage + config/key backups (FR-OPS-005). Record each run in `backup_runs`.
- Restore verification runs regularly and records evidence in `restore_tests`
  (FR-OPS-006). A backup is not considered valid until a restore test passes
  (`operations.restore_verification_failed` otherwise).
- Recovery drill: restore latest verified backup to an isolated environment,
  replay outbox/inbox, rebuild search/report projections, reconcile payment
  provider statements, then validate invariants.

## 7. Object storage lifecycle

Private buckets by default; public derivatives only after malware scan +
transformation. Signed URLs are short-lived. Lifecycle: transition old exports and
evidence to cold storage; purge per retention policy honoring legal holds
(`legal_holds`, BR-043).

## 8. Observability

- Correlation ID propagates browser → HTTP → Action → DB txn → outbox → job →
  provider → callback → audit (`X-Correlation-Id`, BR-050).
- Structured logs with redaction of secrets/PII (NFR-SE-022).
- Metrics (prompt §20): registration/login/MFA outcomes, authorization denials by
  permission+scope, order-placement outcomes, reservation conflicts, payment
  initiation/callback/reconciliation, queue age/failure, search indexing delay,
  shipment SLA, return/refund duration, notification failures, import/export
  outcomes, seller isolation denials, payout exceptions.
- Health endpoints: `/up` (framework) + component health results in
  `health_check_results` (app/db/cache/queue/search/storage/integrations,
  FR-OPS-001). No secrets or infrastructure topology exposed.

## 9. Incident response

Declare via the AUD incident workflow (`incidents` + `incident_events`), preserve
evidence, place legal holds where relevant, communicate, restore, reconcile, and
complete review (FR-AUD-028 / UC-22).

## 10. Marketplace activation gate

Marketplace onboarding, ledger and payout remain disabled (`feature('marketplace')`
= false) until legal, tax, payment-settlement, consumer-protection, support and
operational readiness are configured and governance approves (SDD ADR-020,
decision D-005). Enabling is a controlled, audited configuration change.
