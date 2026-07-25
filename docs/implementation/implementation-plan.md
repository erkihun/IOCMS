# IOCMS — Implementation Plan

Scope: build the production-quality IOCMS modular monolith per IOCMS-SRS/SDD/LLD. 32 modules, 273 tables, 330 FRs, 115 NFRs, 50 business rules, 152 error codes. Delivered in 6 waves per LLD §4.1 / prompt §17, each with an exit gate and full quality-gate run.

## Phase 0 — Foundation scaffold (before Wave 1)

1. Create Laravel 12 app (PHP 8.4, `strict_types`) with Inertia 2 + React + TypeScript (strict) + Tailwind + Vite starter.
2. Establish LLD §3.2 source structure: `app/Domain/*`, `app/Platform/*`, `app/Experience/Pwa`, `app/Http/*`, `app/Support/*`; route files `web/admin/warehouse/seller/api/webhooks.php`; `resources/js/{Pages,Components,Layouts,Features,Hooks,Types}`; `tests/{Unit,Feature,Integration,Architecture,Performance,Security}`.
3. Configure MySQL (prod/local) + SQLite (test), Redis, Horizon, Scout (db/meilisearch), object storage; register routes/middleware in `bootstrap/app.php`.
4. Install Pest, Pint, Larastan/PHPStan, ESLint, tsc; env examples for local/testing/staging/production.
5. Build cross-cutting `Support` primitives: `Money`, `Uuid` (UUIDv7), `Clock`, `Idempotency`, `Correlation`, `Exceptions`, error contract, `SecurityHeaders`, correlation middleware, base `Action`/`Data`/`Query`/`DomainEvent`, Outbox/Inbox infrastructure, `AuthorizationDecision`, `FeatureGate`.

## Wave 1 — Foundation (IAM, ADM, AUD, LOC, OPS, INT)
Identity/auth/MFA/sessions/roles/permissions/scope; typed versioned audited configuration + feature flags + numbering; audit events + maker-checker approvals + privacy requests + legal holds + incidents; locales/translations/currency/calendar; health/backup/restore/jobs; integration clients + webhooks + **transactional outbox/inbox** + idempotency.
**Exit gate:** auth+MFA+permissions+scope work; config typed/versioned/audited; audit+outbox foundations work; locale/calendar work; queue/health/logging/integration foundations work; foundation security tests pass.

## Wave 2 — Catalog (CAT, PRD, SRC, PRI)
Category tree + brands + attributes + units; products/variants/media/lifecycle/policy metadata; search projection (Scout) + merchandising + synonyms; price lists + tax classes/rules + promotions + coupons + deterministic pricing.
**Exit gate:** products reviewable/publishable; variants/attributes/media/policies work; search projection rebuildable; eligibility revalidated against authoritative data; pricing deterministic and tested.

## Wave 3 — Commerce (CUS, CRT, CHK, ORD, PAY)
Customer profiles/addresses/consent/wishlist; cart + saved items + merge; checkout with idempotent atomic order placement (lock→reprice→reserve→snapshot→outbox→commit→pay-after-commit); order lifecycle/state machine/snapshots/invoices; payment attempts/events/refunds/ledger/reconciliation/COD/manual, provider port + fakes.
**Exit gate:** guest+registered checkout work; totals transparent/reproducible; concurrent checkout no oversell; payment callbacks signed/idempotent/reconcilable; order+payment transitions enforced.

## Wave 4 — Fulfillment (INV, WHM, SHP, RET)
Inventory items/movements/reservations/counts/adjustments/thresholds; warehouses/bins/suppliers/PO/receiving/pick/pack/transfer; shipping zones/methods/rates/shipments/tracking/delivery/proof; returns eligibility/authorization/inspection/disposition/recall + refund initiation linked to approved outcomes.
**Exit gate:** inventory movements auditable; receiving/allocation/pick/pack work; dispatch/tracking/POD work; return inspection/disposition work; refund linked to approved return; recall/quarantine work.

## Wave 5 — Engagement & Ops (REV, SUP, NTF, CMS, MKT, RPT, DIE, PWA)
Moderated reviews/questions; support cases with controlled order context; notifications + consent + provider delivery; content/policy versioning + SEO; marketing/segments/loyalty; reporting/governed metrics/exports; import/export staging→validate→preview→commit/rollback; responsive PWA + service worker (never caches sensitive).
**Exit gate:** moderated engagement; controlled support context; notification consent+delivery; content/policy versioning; governed reports; import staging/preview/commit; authorized queued protected exports; storefront a11y+perf checks.

## Wave 6 — Marketplace (SOB, SEL, COM, DSP, MVO) — feature-gated
Seller onboarding/verification/governance; seller portal + seller-scoped commerce; commission/ledger/payout; disputes/claims/protection; multi-vendor cart/order-split/fulfillment. **Disabled behind `feature('marketplace')` + governance gate.** Seller isolation tested as a security boundary.
**Exit gate:** modules exist, gated off, seller isolation tests pass, financial ops cannot run until governance enables.

## Cross-cutting, every wave
- Server-side authz (permission+scope+state+MFA+step-up+maker-checker+feature+env) on every protected op.
- Money as fixed precision; UTC time; UUIDv7 public IDs + human refs; immutable snapshots; no hard-delete of financial/inventory/audit evidence.
- Audit + outbox in the same transaction as material state changes; correlation IDs propagated.
- Localization (Amharic+English) + WCAG 2.2 AA for approved journeys.
- Update `progress.md`, `requirement-traceability.md`, `decision-register.md` after each meaningful group.
- Run all quality gates at each wave exit; fix failures before proceeding; never weaken analysis/tests to go green.

## Delivery discipline
Given the breadth, each module is implemented to real behavior (not CRUD stubs): migrations + models + enums + DTOs + Actions + Form Requests + Policies + Resources/typed props + events/listeners/jobs + factories/seeders + Pest tests, then traceability updated. Modules are not marked Verified until their DoD (test-strategy.md) is met.
