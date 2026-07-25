# IOCMS — Module Dependency Map

Source: IOCMS-LLD-001 §4 (module inventory + dependencies) and §5 (per-module dependencies), reconciled with IOCMS-SDD-001 §5.4/§14.2 bounded contexts. Collaboration across modules is **only** via Actions, query contracts, ports, or after-commit events — never direct foreign-table writes (SDD AP-01, LLD §3.2).

## 32 modules → source packages (LLD §3.2, adopted verbatim)

| Prefix | Module | Package | Phase | Wave |
|--------|--------|---------|-------|------|
| IAM | Identity, Authentication & Authorization | `App\Domain\Identity` | MVP | 1 |
| ADM | Administration & System Configuration | `App\Domain\Administration` | MVP | 1 |
| AUD | Audit, Compliance & Maker-Checker | `App\Domain\Governance` | MVP | 1 |
| LOC | Localization, Currency & Calendar | `App\Domain\Localization` | MVP | 1 |
| OPS | Operations, Monitoring, Backup & Recovery | `App\Platform\Operations` | MVP | 1 |
| INT | External Integrations, APIs & Webhooks | `App\Platform\Integration` | MVP | 1 |
| CAT | Catalog Taxonomy, Brands & Attributes | `App\Domain\Catalog` | MVP | 2 |
| PRD | Product, Variant & Media Management | `App\Domain\Product` | MVP | 2 |
| SRC | Search, Browse, Filtering & Recommendations | `App\Domain\Search` | MVP | 2 |
| PRI | Pricing, Tax, Promotions & Coupons | `App\Domain\Pricing` | MVP | 2 |
| CUS | Customer Profile & Address Management | `App\Domain\Customer` | MVP | 3 |
| CRT | Shopping Cart & Saved Items | `App\Domain\Cart` | MVP | 3 |
| CHK | Checkout & Order Placement | `App\Domain\Checkout` | MVP | 3 |
| ORD | Order Lifecycle & Fulfillment Orchestration | `App\Domain\Order` | MVP | 3 |
| PAY | Payment Processing, Refunds & Reconciliation | `App\Domain\Payment` | MVP | 3 |
| INV | Inventory Control & Stock Reservation | `App\Domain\Inventory` | MVP | 4 |
| WHM | Warehouse, Procurement & Fulfillment Ops | `App\Domain\Warehouse` | MVP | 4 |
| SHP | Shipping, Delivery & Proof of Delivery | `App\Domain\Shipping` | MVP | 4 |
| RET | Returns, Exchanges, Refund Eligibility & Recall | `App\Domain\Returns` | MVP | 4 |
| REV | Ratings, Reviews & Product Questions | `App\Domain\Engagement` | MVP | 5 |
| SUP | Customer Service & Case Management | `App\Domain\Support` | MVP | 5 |
| NTF | Notifications & Communication Preferences | `App\Domain\Notification` | MVP | 5 |
| CMS | Content Management, Policies & SEO | `App\Domain\Content` | MVP | 5 |
| MKT | Marketing, Segmentation & Loyalty | `App\Domain\Marketing` | MVP | 5 |
| RPT | Reporting, Analytics & Data Exports | `App\Domain\Reporting` | MVP | 5 |
| DIE | Bulk Import, Export & Data Exchange | `App\Platform\DataExchange` | MVP | 5 |
| PWA | Responsive Web & Progressive Web App | `App\Experience\Pwa` | MVP | 5 |
| SOB | Seller Onboarding, Verification & Governance | `App\Domain\Marketplace` | Marketplace | 6 |
| SEL | Seller Portal & Seller-Scoped Commerce | `App\Domain\Marketplace` | Marketplace | 6 |
| COM | Commission, Seller Ledger & Payout | `App\Domain\Marketplace` | Marketplace | 6 |
| DSP | Marketplace Disputes, Claims & Protection | `App\Domain\Marketplace` | Marketplace | 6 |
| MVO | Multi-Vendor Cart, Order Split & Fulfillment | `App\Domain\Marketplace` | Marketplace | 6 |

## Cross-cutting Support packages (technical primitives only — no business rules)

`App\Support\Money`, `App\Support\Uuid`, `App\Support\Clock`, `App\Support\Idempotency`, `App\Support\Correlation`, `App\Support\Exceptions`. Plus platform infrastructure owned by Governance/Integration: transactional **Outbox** and idempotent **Inbox** (`outbox_events`, `inbox_events` in INT), audit (`audit_events` in AUD).

## Dependency edges (principal, from LLD §4)

```
Foundation (no domain deps): IAM → {NTF, AUD, ADM, Redis}   ADM → {AUD, IAM, OPS, NTF}
  AUD → {all business modules (evidence), IAM, NTF, object storage}
  LOC → {IAM, all UI, RPT, NTF}   OPS → {all runtime}   INT → {all domain, Redis, secret mgr}

Catalog:  CAT → {LOC, PRD, SRC, AUD}   PRD → {CAT, LOC, PRI, INV}
  SRC → {CAT, PRD, PRI, INV}   PRI → {PRD, CUS/segments, LOC, CRT}

Commerce: CUS → {IAM, LOC, ORD, NTF}   CRT → {PRD, PRI, INV, CUS}
  CHK → {CRT, CUS, PRI, INV}   ORD → {CHK, PRI, INV, PAY}   PAY → {ORD, CHK, RET, INT}

Fulfillment: INV → {PRD, WHM, CHK, ORD}   WHM → {INV, ORD, SHP, AUD}
  SHP → {ORD, WHM, INV, CUS address}   RET → {ORD, SHP, INV, PAY}

Engagement: REV → {ORD, PRD, seller scope, NTF}   SUP → {CUS, ORD, PAY, SHP}
  NTF → {all event producers, LOC, CUS consent, INT}   CMS → {LOC, AUD, PRD, PWA/edge}
  MKT → {CUS consent, CRT, ORD, PRD/INV/PRI}   RPT → {all authoritative, AUD, storage, queue}
  DIE → {target domains, AUD, storage, queue}   PWA → {customer web, NTF, CMS, SRC}

Marketplace (feature-gated): SOB → {IAM, AUD, storage, NTF}   SEL → {SOB, IAM, PRD, INV}
  COM → {ORD, PAY, RET, DSP}   DSP → {ORD, PAY, SHP, RET}   MVO → {CRT, CHK, ORD, PRI}
```

## Boundary rules (enforced by architecture tests — LLD §11.2)

1. `App\Domain\*` must not depend on `App\Http\Requests`, React, or provider SDKs.
2. Provider SDKs confined to `App\Platform\Integration\*` adapters; domains depend on ports.
3. A module may **read** another module through a published Query contract but never write its tables.
4. `App\Support\*` contains no business rules.
5. No circular module dependency — cycles are broken via after-commit events (e.g. PAY↔ORD, RET↔PAY).
6. Marketplace domain code exists but is gated by `feature('marketplace')` + governance flags (SDD ADR-020).

## Acyclic wave ordering rationale

Waves follow the LLD delivery sequence: each wave depends only on completed earlier waves. PAY↔RET and PAY↔ORD apparent cycles are resolved because collaboration is via events/contracts, not compile-time dependencies; RET's refund *initiation* calls a PAY contract, PAY's capture emits an event ORD consumes.
