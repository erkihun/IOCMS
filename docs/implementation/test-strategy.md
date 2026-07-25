# IOCMS — Test Strategy

Derived from SDD §15.5 (10 test layers), LLD §11 (test layers + architecture tests + module DoD), and prompt §18 (critical required tests). Pest is the primary PHP framework. No skipped test may present a false green build (prompt §18, §27); environmental skips carry an explicit reason and an alternative covering test.

## Test layers (`tests/` per LLD §3.2)

| Directory | Layer | Coverage |
|-----------|-------|----------|
| `tests/Unit` | Unit / domain invariants | Money, pricing, promotion, eligibility, state guards, value objects, pure policies, availability math |
| `tests/Feature` | HTTP/API | Validation, authorization, transactions, persistence, localization, user outcomes, form requests, policies |
| `tests/Integration` | Infra + cross-module | DB constraints, Redis, search, storage, module collaboration via contracts/events |
| `tests/Architecture` | Boundaries | Namespace/dependency rules, no-raw-SQL, no-float-money, public-id binding, controllers thin |
| `tests/Performance` | Perf profiles | Repeatable scripts for search P95, checkout throughput, webhook burst (scaffolded scripts, not claims) |
| `tests/Security` | Security | ASVS evidence, authz matrix, IDOR, headers, CSRF, log redaction, no-card-data, seller isolation |

## Critical required tests (prompt §18) — allocation

| Required test | Location | Wave |
|---------------|----------|------|
| Concurrent registration, same normalized identifier | `tests/Feature/IAM/ConcurrentRegistrationTest` | 1 |
| Verification-token expiry & replay | `tests/Feature/IAM/VerificationTokenTest` | 1 |
| MFA enrollment, recovery, step-up | `tests/Feature/IAM/Mfa*Test` | 1 |
| Permission + record-scope matrices | `tests/Security/AuthzMatrixTest` | 1 |
| Maker-checker separation | `tests/Feature/AUD/MakerCheckerTest` | 1 |
| Configuration version immutability | `tests/Feature/ADM/ConfigVersionTest` | 1 |
| Concurrent final-stock purchase (no oversell) | `tests/Integration/INV/ConcurrentPurchaseTest` | 3/4 |
| Duplicate checkout request | `tests/Feature/CHK/DuplicateCheckoutTest` | 3 |
| Idempotency-key fingerprint mismatch | `tests/Feature/CHK/IdempotencyFingerprintTest` | 3 |
| Duplicate & out-of-order payment callbacks | `tests/Feature/PAY/CallbackOrderingTest` | 3 |
| Payment-provider timeout + later reconciliation | `tests/Feature/PAY/TimeoutReconcileTest` | 3 |
| Coupon concurrency & usage limits | `tests/Integration/PRI/CouponConcurrencyTest` | 2 |
| Inventory reservation expiry | `tests/Feature/INV/ReservationExpiryTest` | 4 |
| Pick/pack quantity conflicts | `tests/Feature/WHM/PickPackConflictTest` | 4 |
| Duplicate shipment events | `tests/Feature/SHP/DuplicateTrackingTest` | 4 |
| Return eligibility boundaries | `tests/Feature/RET/ReturnEligibilityTest` | 4 |
| Double-refund prevention | `tests/Feature/PAY/DoubleRefundPreventionTest` | 3/4 |
| Seller cross-scope access attempts | `tests/Security/SEL/SellerScopeTest` | 6 |
| Duplicate commission posting | `tests/Feature/COM/DuplicateCommissionTest` | 6 |
| Payout approval separation | `tests/Feature/COM/PayoutApprovalTest` | 6 |
| Import rollback on validation failure | `tests/Feature/DIE/ImportRollbackTest` | 5 |
| Unauthorized export attempts | `tests/Security/DIE/ExportAuthTest` | 5 |
| Audit-log immutability | `tests/Feature/AUD/AuditImmutableTest` | 1 |
| Backup restoration verification | `tests/Feature/OPS/RestoreVerificationTest` | 1 |

## Concurrency test approach (see decision D-002)

Real row-lock/oversell tests need MySQL. They are written to (a) run fully against MySQL (parallel HTTP requests / `SELECT … FOR UPDATE`), and (b) on the SQLite test connection, assert the equivalent **atomic conditional update** invariant (`UPDATE … WHERE available >= qty`) deterministically, so the invariant is always covered. MySQL-only assertions are guarded and skip with an explicit reason when the driver is not MySQL — never a silent pass.

## Definition of Done per module (LLD §11.3)

A module is **Verified** only when: code exists; migration/data behavior exists; authorization (permission+scope+state) exists; required audit/outbox behavior exists; unit + feature + relevant integration/security tests exist and pass; and traceability points to the exact artifacts.

## Quality gates (run each wave, prompt §25)

`composer validate` · `composer install` · `php artisan optimize:clear` · `php artisan migrate:fresh --seed --env=testing` · `php artisan test` · `vendor/bin/pint --test` · `vendor/bin/phpstan analyse` · `npm ci` · `npm run lint` · `npm run typecheck` · `npm run build` · `php artisan route:list` · `php artisan about`.
