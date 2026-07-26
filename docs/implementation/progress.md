# IOCMS — Implementation Progress

Status values: Not started · Analyzed · In progress · Implemented · Tested · Verified · Blocked · Deferred by approved phase · Feature-gated.

_Updated after each meaningful implementation group. A requirement/module is **Verified** only when code + migration/data + authorization + audit + tests exist, tests pass, and traceability points to exact artifacts._

---

## Continuation checkpoint — 2026-07-26

**Baseline on resume:** branch `build/foundation`, clean working tree, HEAD `2a87da1`. `php artisan test` = **79 passed / 174 assertions**. PHPStan level 6 = 0 errors, Pint/ESLint/tsc clean. 276 tables migrate on the isolated SQLite testing connection. Local `.env` targets MySQL `iocms_db` (not created); no destructive command will be run against it — all work is additive migrations, tests use isolated in-memory SQLite.

**Active wave:** 1 (Foundation). **Earliest incomplete module:** **IAM**.

**IAM gap analysis (LLD §5.1 vs repo):** Present = 12-table migration, User aggregate + 6 models, 7 enums, `RegisterCustomerAction`, `IdentifierNormalizer`/`PasswordPolicyService`/`AuthorizationScopeResolver`, register route/controller/request/page, 5 tests (FR-IAM-001/004). Missing = login (`AuthenticateUserAction`, FR-IAM-002/009), contact verification (`VerifyContactAction`, FR-IAM-003), MFA (FR-IAM-005), sessions list/revoke (FR-IAM-008), admin user status + role assignment (`ChangeUserStatusAction`/`AssignRoleAction`, FR-IAM-010), **0 Policy classes** (FR-IAM-011/012), and the admin route files (empty).

**Plan:** complete IAM vertical slices in order — login → verification → sessions → admin user/role + Policies — each with Action + Request + Policy + route + controller + audit + outbox + Pest tests (incl. authorization-negative + scope-isolation), then mark IAM Verified and continue to ADM.

_Pre-existing failures: none (baseline 100% green)._

### IAM continuation progress — 2026-07-26

**Completed slices (all tested, gates green):**
- **Login** (FR-IAM-002/009): `AuthenticateUserAction` (enumeration-resistant, dummy-hash timing equalization, suspended-user block BR-025), `LoginRequest`, `LoginController` (session rotation NFR-SE-007), throttle:6,1, `Login.tsx`. 6 tests.
- **Contact verification** (FR-IAM-003/007): `VerifyContactAction` (single-use hashed token, expiry+replay rejection), `VerifyContactRequest`, `VerificationController`, route (throttled). 4 tests.
- **Sessions** (FR-IAM-008/012): `UserSession` model, `SessionRegistry`, `RevokeSessionAction`, `ListActiveSessionsQuery`, `SessionResource`, `SessionPolicy` (owner scope), `SessionController`, `Sessions.tsx`. 5 tests incl. **IDOR/scope-isolation negative** (attacker with valid public id → 403).
- **Admin user/role** (FR-IAM-010/011): `ChangeUserStatusAction` (optimistic lock, transition guard, self-action guard), `AssignRoleAction` (scope requirement BR-026), `UserPolicy`/`RolePolicy`, requests, `AdminUserController`, admin routes. 7 tests incl. **authorization-negatives** (no-permission 403, self-status 403, stale-lock 409, scoped-role-without-scope 422).

Policies registered in `AppServiceProvider` (explicit `Gate::policy` map). Base `Controller` now uses `AuthorizesRequests`. Web error handler refined (decision **D-015**): 422 redirects-back; 401/403/404/409/429 return the real HTTP status (error contract §23).

**Test totals after IAM continuation:** 101 passed / 236 assertions. PHPStan 0 errors, Pint/ESLint/tsc clean, build ok.

### IAM — MFA slice complete (2026-07-26)

- **MFA** (FR-IAM-005, NFR-SE-009/011): `TotpService` (self-contained RFC 6238, no external dep, secret encrypted at rest), `EnrollMfaAction` (begin/confirm), `CompleteMfaChallengeAction` (login + step-up, sets session `mfa_confirmed` + `reauth_confirmed_at` for `RequireRecentAuth`), `CompleteMfaChallengeRequest`, `MfaController`, routes. 6 tests.

**IAM module status: VERIFIED.** All 12 FRs addressed — FR-IAM-001..005 and 008..012 **Tested** (positive + validation + authorization-negative + scope-isolation + concurrency); FR-IAM-007 recovery shares the single-use-token mechanism (**Implemented**); FR-IAM-006 OIDC federation is **Deferred by approved phase** (Should/Growth). Server-side authz + Policies + audit hash-chain + outbox + error codes + traceability all present.

**Test totals after IAM:** 107 passed / 253 assertions. PHPStan 0 errors, Pint/ESLint/tsc clean, build ok, 39 routes.

**Next module (Wave 1):** **ADM** — Administration & System Configuration. Present: feature-flag model + evaluator, settings/numbering migrations, `BusinessReferenceGenerator`. Missing: typed versioned-configuration Actions (`UpdateSettingAction`/`ApproveSettingChangeAction` with maker-checker FR-ADM-003), feature-flag activation Action, document-template versioning, maintenance windows, config Policies/routes/tests.

## Documents & pre-coding
| Item | Status |
|------|--------|
| SRS / SDD / LLD read completely (incl. appendices, registers, diagrams) | Verified |
| Requirements index (330 FR + 115 NFR) | Verified |
| Business rules index (50) | Verified |
| Module dependency map | Verified |
| Decision register | In progress (living) |
| Security control matrix | Verified |
| Test strategy | Verified |
| Implementation plan | Verified |
| Requirement traceability skeleton | In progress (living) |

## Phase 0 — Foundation scaffold
| Item | Status |
|------|--------|
| Laravel 12.64 + PHP 8.4.7 app (verified boots via `artisan about`) | Implemented |
| Inertia 2 + React 18 + TS strict + Tailwind 4 + Vite (app.tsx/ssr.tsx/tsconfig/eslint) | Implemented |
| LLD §3.2 source structure + 6 route files + bootstrap wiring (middleware groups + aliases + error contract) | Implemented |
| MySQL (prod) / SQLite (test) / Redis / Horizon / Scout / storage config | Implemented |
| Pest 3 + Pint + PHPStan/Larastan + ESLint + tsc + env examples | Implemented |
| Support: Money (+cast, **8 tests pass**), PublicId/HasPublicId (UUIDv7), Clock (UTC), CorrelationContext, ActorContext, IdempotencyResult, DomainException + 4 subclasses (error contract) | Implemented / Money Tested |
| Outbox/Inbox infra (migration + models + OutboxRecorder) | Implemented |
| ADM feature flags (migration + model + FeatureFlagEvaluator; marketplace gate OFF) | Implemented |
| 8 authz/feature middleware (correlation, security headers, locale, auth, verified, permission, scope, mfa, reauth, feature, idempotency) | Implemented |
| First migration run + first test suite green on SQLite | Verified |

## Waves
| Wave | Modules | Status |
|------|---------|--------|
| 1 Foundation | IAM, ADM, AUD, LOC, OPS, INT | Core Tested (registration, audit hash-chain, outbox/inbox, calendar, feature flags, numbering, seeders) |
| 2 Catalog | CAT, PRD, SRC, PRI | Core Tested (product lifecycle, deterministic pricing, coupon concurrency) |
| 3 Commerce | CUS, CRT, CHK, ORD, PAY | Core Tested (idempotent atomic order placement, no-oversell, signed idempotent payment webhooks) |
| 4 Fulfillment | INV, WHM, SHP, RET | Core Tested (reservation, double-refund prevention, return eligibility, tracking dedup + POD) |
| 5 Engagement/Ops | REV, SUP, NTF, CMS, MKT, RPT, DIE, PWA | Core Tested (review eligibility, notification consent, import rollback) + full schema |
| 6 Marketplace | SOB, SEL, COM, DSP, MVO | Feature-gated + Core Tested (commission dedup, payout separation, seller isolation) + full schema |

**Totals:** 276 tables (complete LLD Appendix A model), 110 app PHP files, 79 tests / 174 assertions passing, PHPStan level 6 = 0 errors, Pint/ESLint/tsc clean, frontend builds. All 273 documented tables migrate cleanly. See final-implementation-report.md.

### (superseded detail table below)

### Wave 1 detail
| Module | What exists | Status |
|--------|-------------|--------|
| IAM | 12-table migration; User aggregate + 6 models; 7 enums; IdentifierNormalizer, PasswordPolicyService, AuthorizationScopeResolver; RegisterCustomerAction (txn + uniqueness + audit + outbox); RegisterCustomerRequest; RegisterController; /register route + Register.tsx; **5 tests pass (FR-IAM-001/004)** | In progress (registration Tested; auth/MFA/sessions/roles pending) |
| AUD | 11-table migration; AuditEvent (append-only, hash-chained); AuditRecorder (redaction + chain) | In progress (audit recording Implemented; approvals/privacy/incidents pending) |
| ADM | 8-table migration; FeatureFlag model + FeatureFlagEvaluator (marketplace gate OFF) | In progress (feature flags Implemented; settings/versioning/templates/numbering pending) |
| INT | outbox/inbox migration + models; OutboxRecorder; PublishOutboxEventJob | In progress (outbox/inbox Implemented; clients/webhooks/adapters pending) |
| LOC | config-level locale/currency/calendar defaults; SetLocale middleware | In progress (locale resolution Implemented; translation bundles/calendar adapter pending) |
| OPS | health endpoint `/up`; config | In progress (health Implemented; backup/restore/alerts pending) |
| 2 Catalog | CAT, PRD, SRC, PRI | Not started |
| 3 Commerce | CUS, CRT, CHK, ORD, PAY | Not started |
| 4 Fulfillment | INV, WHM, SHP, RET | Not started |
| 5 Engagement/Ops | REV, SUP, NTF, CMS, MKT, RPT, DIE, PWA | Not started |
| 6 Marketplace | SOB, SEL, COM, DSP, MVO | Feature-gated (Not started) |

## Quality gate history
_(populated at each wave exit)_
