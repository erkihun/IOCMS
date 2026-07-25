# IOCMS — Security Control Matrix

Maps each of the 28 SRS security NFRs (NFR-SE-001…028) and the prompt §10 baseline to concrete controls, the module/component that owns them, and the verifying test. Target: OWASP ASVS 5.0 Level 2 for public commerce (NFR-SE-001). Built into each module, not a final cleanup phase.

| NFR / Control | Requirement | Implementing control | Owner | Test |
|---------------|-------------|----------------------|-------|------|
| NFR-SE-001 | ASVS L2 for public commerce | Whole-app control set below; architecture + security test suites | All | `tests/Security/*` |
| NFR-SE-002 | TLS everywhere; reject insecure transport | `URL::forceScheme(https)` in prod; HSTS header; `Strict-Transport-Security` | Middleware `SecurityHeaders` | `tests/Security/HeadersTest` |
| NFR-SE-003 | Secure headers + tested CSP | `SecurityHeaders` middleware: CSP, X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy | Http/Middleware | `tests/Security/HeadersTest` |
| NFR-SE-004 | Server-side input validation/normalization | Form Requests + API schema on every mutation; `IdentifierNormalizer` | Http/Requests, Domain services | `tests/Feature/*/RequestTest` |
| NFR-SE-005 | Parameterized queries + output encoding | Eloquent/query builder only (no raw string SQL); React auto-escaping; Inertia typed props | All | `tests/Architecture/NoRawSqlTest` |
| NFR-SE-006 | CSRF for cookie-auth state changes | Laravel `web` CSRF middleware; Inertia XSRF token | Bootstrap | `tests/Security/CsrfTest` |
| NFR-SE-007 | Secure/HttpOnly/SameSite cookies; session rotation | `session.secure/http_only/same_site`; `session()->regenerate()` after auth & privilege change | Config + AuthenticateUserAction | `tests/Feature/IAM/SessionRotationTest` |
| NFR-SE-008 | Inactive + absolute session expiry (configurable) | `session.lifetime` + absolute-lifetime guard; `PruneExpiredSessionsJob` | IAM | `tests/Feature/IAM/SessionExpiryTest` |
| NFR-SE-009 | MFA for admins/finance/payout approvers | `RequireMfa` middleware on privileged route groups; enrollment enforced | IAM | `tests/Feature/IAM/MfaRequiredTest` |
| NFR-SE-010 | Phishing-resistant authenticators where possible | TOTP + WebAuthn-ready `AuthenticatorType` enum (TOTP implemented; WebAuthn port) | IAM | `tests/Feature/IAM/MfaEnrollTest` |
| NFR-SE-011 | Recent reauthentication for high-risk actions | `RequireRecentAuth` middleware (bank change, large refund, payout approve, MFA reset, role elevation) | IAM | `tests/Feature/IAM/StepUpTest` |
| NFR-SE-012 | Least privilege, deny-by-default | Gate denies unless permission+scope grant; every Action authorizes via Policy | IAM/Governance | `tests/Security/AuthzMatrixTest` |
| NFR-SE-013 | No IDOR — ownership/scope check per record | Route binding on `public_id` + Policy scope check; query scoping | All Policies | `tests/Security/IdorTest` |
| NFR-SE-014 | Segregate customer/staff/seller/machine privileges; no route-inferred authz | `UserType` + `ScopeType` in authorization decision; separate route files/guards | IAM | `tests/Security/PrivilegeSegregationTest` |
| NFR-SE-015 | Encrypt restricted personal/bank/secret at rest | Laravel `encrypted` casts on PII/bank fields; app key via secret manager | CUS, SOB, COM | `tests/Feature/SOB/EncryptedBankTest` |
| NFR-SE-016 | Secrets outside code + config views; rotation | `.env`/secret-manager references; `configuration.secret_not_editable` guard blocks editing secrets as settings | ADM | `tests/Feature/ADM/SecretNotEditableTest` |
| NFR-SE-017 | No prohibited payment data; minimize PCI scope | No card/CVV/OTP columns anywhere; tokenized providers; `BR-023` | PAY | `tests/Security/PAY/NoCardDataTest` |
| NFR-SE-018 | File content/extension/MIME/size validation; store outside webroot | `FileValidationService`; private disk; no executable public paths | Support + PRD/RET/SUP | `tests/Security/FileUploadTest` |
| NFR-SE-019 | Malware scan uploaded files | `MalwareScanPort` (fake clean/infected adapter) invoked before availability | Support/Integration | `tests/Feature/MediaScanTest` |
| NFR-SE-020 | Rate limits on login/registration/OTP/search/checkout/review/support/API | Named rate limiters per route group | Bootstrap | `tests/Feature/RateLimitTest` |
| NFR-SE-021 | Security event logging protected from ordinary users | `audit_events` append-only; no update/delete route; DB privilege note | AUD | `tests/Feature/AUD/AuditImmutableTest` |
| NFR-SE-022 | Mask passwords/tokens/secrets/PII in logs | `LogRedactor` processor; never log request payloads with secrets | Support/Observability | `tests/Security/LogRedactionTest` |
| NFR-SE-023 | Generic external errors + protected diagnostics | Error contract: safe code+message+correlation_id externally; detail server-side | Support/Exceptions | `tests/Feature/ErrorContractTest` |
| NFR-SE-024 | Vuln scan/dependency/code review/pentest hooks | CI stages: `composer audit`, phpstan, pint, npm audit, security tests | CI | `.github/workflows/ci.yml` |
| NFR-SE-025 | Dependency inventory + remediation targets | Lock files committed; `composer audit` gate | CI | CI |
| NFR-SE-026 | Privacy notices, lawful-purpose, consent history, minimization | `consent_records`, purpose gating on marketing | CUS/AUD | `tests/Feature/CUS/ConsentTest` |
| NFR-SE-027 | DSAR: access/correction/export/objection/deletion w/ retention + identity check | `privacy_requests` workflow + legal-hold aware | AUD | `tests/Feature/AUD/PrivacyRequestTest` |
| NFR-SE-028 | Breach recording/assessment/evidence/notification workflow | `incidents` + `incident_events` | AUD/OPS | `tests/Feature/AUD/IncidentTest` |

## Cross-cutting authorization decision (SDD ADR-016, LLD §9.1)

Every protected server operation evaluates, in order: authenticated actor → actor type → permission → record scope → resource ownership → current resource state → MFA status → recent reauthentication → maker-checker separation → feature gate → environment restriction. Implemented in `AuthorizationDecision` used by all Policies. Negative tests exist for every major policy (prompt §11).
