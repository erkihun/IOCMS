# IOCMS — Implementation Progress

Status values: Not started · Analyzed · In progress · Implemented · Tested · Verified · Blocked · Deferred by approved phase · Feature-gated.

_Updated after each meaningful implementation group. A requirement/module is **Verified** only when code + migration/data + authorization + audit + tests exist, tests pass, and traceability points to exact artifacts._

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
| 1 Foundation | IAM, ADM, AUD, LOC, OPS, INT | In progress |

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
