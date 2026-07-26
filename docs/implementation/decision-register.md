# IOCMS — Decision Register

Records every material interpretation, documentation inconsistency, assumption, and open provider/legal decision. Governed by the prompt's authority hierarchy: mandatory SRS behavior wins; SDD wins high-level design; LLD wins implementation detail where not in conflict; apply the stricter security/privacy/integrity/audit rule.

Status legend: **Applied** (decided and implemented) / **Assumption** (safe default chosen, configurable) / **Open** (needs external secret, legal, or provider input — implemented as neutral port + disabled gate).

---

## D-001 — PHP 8.4 runtime resolution (Applied)
**Context:** System default `php` on PATH is XAMPP PHP 8.2.12; the baseline (SRS §3.5, LLD §3.1) mandates PHP 8.4.
**Decision:** Use Herd's PHP 8.4.7 (`C:\Users\Erkis\.config\herd\bin\php84\php.exe`) for all `composer`, `artisan`, `pest`, and analysis commands. All extensions required by the baseline are present (pdo_mysql, redis, gd, zip, bcmath, mbstring, intl, sodium, curl, sqlite3). `composer.json` pins `"php": "^8.4"`.
**Consequence:** Build fully complies with the PHP 8.4 baseline. `pdo_sqlite` is present, enabling an in-memory SQLite test connection so the suite runs without a live MySQL server, while migrations remain MySQL-compatible for production.

## D-002 — Database platform for tests vs production (Applied)
**Context:** LLD §3.1 mandates MySQL 8 with FKs, CHECK constraints, DECIMAL, TIMESTAMP(6). Local dev has XAMPP MySQL; CI/agent test runs benefit from a zero-dependency DB.
**Decision:** Production/staging/local use MySQL 8 (authoritative). The **testing** connection uses SQLite in-memory. Migrations are written MySQL-first; where a construct is MySQL-only (e.g. `TIMESTAMP(6)`, generated columns, some CHECK expressions), migrations degrade gracefully on SQLite via `Schema::getConnection()->getDriverName()` guards, preserving the invariant in application-layer guards + DB constraint on MySQL.
**Consequence:** Concurrency/locking tests that require real row locks are marked to run against MySQL and skipped (with an explicit skip reason, not a false pass) on SQLite; equivalent atomic-conditional-update logic is unit-tested deterministically. This is recorded per the prohibition on skipped tests presenting a false green build — the skip is environmental, documented, and the invariant is covered by an alternative test.

## D-003 — LLD namespace typos normalized (Applied)
**Context:** LLD §5 class catalogs render namespaces as `App\Domain\Identity\Modelss`, `\Querys`, `\Policys` (auto-pluralization artifacts of the document generator).
**Decision:** Use correct, conventional segment names: `Models`, `Queries`, `Policies`, plus `Actions`, `Data`, `Enums`, `Events`, `Jobs`, `Listeners`, `Services`, `ValueObjects`. This matches SDD §15.1 (which lists `Queries`, `Policies` correctly) and Laravel conventions. Class names, responsibilities, and ownership are preserved exactly.
**Authority:** LLD implementation detail vs SDD — SDD's correctly-spelled structure governs; no requirement weakened.

## D-004 — Source organization: LLD §3.2 adopted verbatim (Applied)
**Context:** SDD §15.1 and LLD §3.2 give slightly different `Domain/` groupings (SDD groups into 12 contexts; LLD lists 25 per-module packages). The prompt's §7 required structure matches LLD §3.2 exactly.
**Decision:** Adopt the LLD §3.2 / prompt §7 structure verbatim (`Domain/Identity`, `Domain/Administration`, … `Domain/Marketplace`; `Platform/{Integration,DataExchange,Operations}`; `Experience/Pwa`; `Support/{Money,Uuid,Clock,Idempotency,Correlation,Exceptions}`). SDD's bounded contexts are honored as logical groupings within these packages.
**Authority:** LLD is authoritative for Laravel source organization.

## D-005 — Marketplace feature gate (Applied)
**Context:** SRS BUS-018..020, SDD ADR-020, LLD §5.28–5.32, prompt §17 Wave 6: marketplace must stay disabled behind feature + governance gates until legal/tax/payment/settlement/support/operational readiness is configured.
**Decision:** Ship marketplace domain code, migrations, and tests, but gate all marketplace onboarding, ledger, and payout operations behind `feature_flags` key `marketplace` (default **off**) AND a governance readiness flag. Routes in `seller.php` and marketplace admin actions return `403 seller.suspended`/feature-disabled when the gate is off. Seller isolation is still tested as a security boundary.
**Consequence:** No marketplace financial operation can run in production until explicitly enabled by governance.

## D-006 — Payment provider = neutral port + fake/sandbox adapter (Open→Applied port)
**Context:** SRS §1.6, FR-PAY-001/004, SDD ADR-008, ADR-007. No licensed Ethiopian provider credentials are available; a browser redirect is never authoritative (BR-004).
**Decision:** Implement a `PaymentGateway` port with: (a) a `FakePaymentGateway` for tests/dev (deterministic, sandbox-like), (b) a `ManualBankTransferGateway` and `CashOnDeliveryGateway` (fully implementable without external provider), (c) config validation + a disabled feature gate for any hosted/tokenized online provider. Webhook signature verification, inbox dedup, replay window, and reconciliation are implemented generically. **Open decision:** the concrete licensed online-payment adapter (e.g. Chapa/Telebirr/etc.) requires provider contract + credentials → left as a documented port with a disabled gate.
**Consequence:** COD, manual bank transfer, and the full idempotent webhook/reconciliation machinery are production-quality; the hosted online rail is a wired-but-disabled seam.

## D-007 — Courier / Messaging / Map / OIDC / Search / Storage providers = ports + fakes (Assumption)
**Decision:** Each external provider (courier, SMS/email/Telegram/web-push, map/geocode, OIDC, Meilisearch, S3) is behind a port with a fake adapter and config-validated real adapter. Search uses Laravel Scout with a database/collection driver by default (rebuildable projection) and a Meilisearch adapter gated on config. Storage defaults to a private local disk in dev with signed-URL semantics; S3 adapter is config-driven. Real provider credentials are secret-manager references, never DB settings.

## D-008 — Open commercial/regulatory values not invented (Open)
**Context:** Prompt §2 forbids inventing tax rates, retention periods, commercial limits, regulatory interpretations.
**Decision:** Tax classes/rules, return windows, reserve windows, retention periods, commission rates, restricted-product rules, and numbering formats are implemented as **typed, versioned, seeded-with-synthetic-defaults configuration** (`system_settings`, `tax_rules`, `commission_rules`, etc.), not hard-coded. Development seeders use obviously synthetic values (e.g. "15% demo VAT", "30-day demo return window") clearly labelled as non-production. Production values are an explicit configuration/legal task.

## D-009 — Money representation (Applied)
**Context:** LLD §3.5 `DECIMAL(20,4)` + currency; prohibition on binary float; SDD §8.3 "fixed-precision decimal or integer minor units".
**Decision:** Persist money as `DECIMAL(20,4)` + `CHAR(3)` currency. In PHP, the `Money` value object operates on integer minor units (via bcmath) internally with an explicit half-up rounding policy at contract boundaries, and serializes to/from the DECIMAL columns. No float arithmetic anywhere in money paths (architecture test asserts).

## D-010 — UUIDv7 + human-readable references (Applied)
**Context:** LLD §3.5, SDD ADR-009. Public IDs are UUIDv7 `CHAR(36)`; display references (order/shipment/return/case/payout/batch numbers) are governed separately and are never an authorization boundary. Route-model binding resolves on `public_id`, never internal `id`.

## D-015 — Web error handler returns real HTTP status for non-422 domain errors (Applied)
**Context:** The initial exception handler redirected ALL web-request `DomainException`s back with a flash error. That is correct for validation/business-rule failures (422) but wrong for authorization (403), not-found (404), conflict (409) and throttle (429), which the error contract (§23, LLD §3.7) says must carry the real status. It also made authorization-negative tests unable to distinguish a 403 from a redirect.
**Decision:** The web renderer now redirects-back only for 422; for 401/403/404/409/429 it returns a response with the correct HTTP status and the safe localized message. API/webhook clients continue to get the structured JSON error contract at the correct status. Two `RegisterCustomerTest` assertions that relied on the old redirect behavior for a duplicate (409) were updated to assert `status 409` (the correct contract) — the implementation was made more correct, not the test weakened.
**Consequence:** Authorization/scope/conflict outcomes are unambiguous to clients and tests; Inertia still surfaces validation errors via redirect-back.

## D-014 — Portable SQL for cross-driver tests (Applied)
**Context:** MySQL-only functions (`GREATEST`) and DECIMAL string formatting differ on the SQLite test connection (D-002). SQLite returns a DECIMAL column as a numeric `40`, MySQL returns the string `'40.0000'`.
**Decision:** Use portable SQL expressions in atomic updates (`CASE WHEN x>0 THEN x-1 ELSE 0 END` instead of `GREATEST`). In tests, compare money columns numerically (`(float) $x`) rather than by exact string, since the authoritative representation is the DECIMAL value, not its textual formatting. Production MySQL still stores/returns DECIMAL(20,4). The `Money` value object remains the in-app source of truth (no float arithmetic in logic — only in test comparison of already-computed persisted values).
**Consequence:** The same test suite runs on SQLite (agent/CI) and MySQL (integration) without weakening any invariant.

## D-013 — LLD `sessions` table renamed `user_sessions` (Applied)
**Context:** LLD §5.1 defines an IAM `sessions` table for active-session management + revocation (FR-IAM-008). Laravel's `database` session driver also uses a table named `sessions` with an incompatible schema.
**Decision:** Name the IAM domain table `user_sessions` (active-session registry) and keep the framework `sessions` table for the session store. Both are created in the IAM migration. Ownership and the FR-IAM-008 behavior are unchanged; only the physical name differs to avoid a collision.
**Authority:** LLD implementation detail; no requirement weakened.

## D-012 — Horizon `ext-pcntl`/`ext-posix` platform override (Applied)
**Context:** Laravel Horizon (baseline-required, SRS/SDD/LLD) needs `ext-pcntl` + `ext-posix`, which are POSIX-only and unavailable on the Windows dev host, but are present on the Linux production/CI target where Horizon actually runs its worker supervisor.
**Decision:** Add `config.platform.ext-pcntl` / `ext-posix` to `composer.json` so the package resolves and installs on Windows for development and static analysis. Horizon's supervisor commands (`horizon`, `horizon:*`) are only invoked on Linux; on Windows dev, queues run via `queue:work`/`queue:listen`.
**Consequence:** Full Horizon dashboard, config, and metrics are available; the platform override is the conventional, documented way to develop Horizon-dependent apps cross-platform without weakening the production requirement.

## D-011 — Reporting/search/cache are derived and rebuildable (Applied)
Per SDD AP-01 / ADR-004/014, MySQL transactional data is authoritative; search index, cache, and report projections are rebuildable and never authoritative for checkout/finance decisions.

---

### Documentation inconsistencies recorded (none weakens a requirement)
- LLD namespace pluralization typos → **D-003** (normalized to conventions; SDD governs).
- SDD §15.1 vs LLD §3.2 Domain grouping → **D-004** (LLD/prompt structure adopted).
- SDD ADR list numbers ADR-001…ADR-020 but one intermediate number is not present in the rendered table — treated as a rendering gap, all present ADRs honored; no design decision lost.
