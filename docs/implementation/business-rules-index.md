# IOCMS — Business Rules Index (BR-001…BR-050)

All 50 invariant business rules from IOCMS-SRS-001 §10.1, each mapped to the owning module(s), the enforcement mechanism (DB constraint / domain guard / policy / action), and the test that proves it. These are **invariants** — they hold across every interface and channel.

| BR | Rule | Owning module(s) | Enforcement mechanism | Verifying test |
|----|------|------------------|-----------------------|----------------|
| BR-001 | A purchasable item is a product variant, not a product master. | PRD, CRT, CHK | Cart/checkout reference `product_variant_id` FK; no order line without a variant. | `tests/Feature/CRT`, `tests/Unit/PRD` |
| BR-002 | Every order item retains a full snapshot (name, SKU, options, image, seller, price, discount, tax, policy versions). | ORD, CHK | Immutable `order_items` snapshot columns written in PlaceOrderAction. | `tests/Feature/CHK/OrderSnapshotTest` |
| BR-003 | Order, payment, shipment, return, refund, payout statuses are independent state machines linked by events. | ORD, PAY, SHP, RET, COM | Separate enum + transition-service per aggregate; no shared status column. | `tests/Unit/*/StateMachineTest` |
| BR-004 | A browser redirect alone cannot mark a payment successful. | PAY | Payment status only advanced by signed webhook / server-to-server confirmation; redirect handler never captures. | `tests/Feature/PAY/RedirectNotAuthoritativeTest` |
| BR-005 | Cumulative refund ≤ captured − prior settled refunds. | PAY, RET | DB CHECK + `refund.exceeds_eligible_amount` guard in RefundAction. | `tests/Feature/PAY/DoubleRefundPreventionTest` |
| BR-006 | A reservation reduces available-to-sell, not on-hand, until consumption. | INV | ATS = on_hand − active reservations − safety; reservation ≠ movement. | `tests/Unit/INV/AvailabilityTest` |
| BR-007 | Expired/cancelled/failed-payment reservations are released exactly once. | INV | `stock_reservations.status` transition guard + idempotent ReleaseReservationAction. | `tests/Feature/INV/ReservationExpiryTest` |
| BR-008 | Negative available stock prohibited unless documented backorder policy active. | INV | `inventory.negative_not_allowed` guard + conditional atomic update. | `tests/Feature/INV/NegativeStockTest` |
| BR-009 | Inventory corrections use reversal/adjustment movements; history not deleted. | INV | `stock_movements` append-only; no hard delete; ReverseMovementAction. | `tests/Feature/INV/MovementImmutabilityTest` |
| BR-010 | delivered ≤ shipped ≤ fulfilled quantity. | ORD, SHP | Line-level quantity guards in shipment/delivery actions. | `tests/Feature/SHP/QuantityLadderTest` |
| BR-011 | Customer may cancel only quantities eligible under fulfillment/payment state. | ORD | CancelOrderAction eligibility guard; `order.cancellation_ineligible`. | `tests/Feature/ORD/CancellationEligibilityTest` |
| BR-012 | Return quantity ≤ delivered − prior accepted returns. | RET | `return.quantity_exceeded` guard. | `tests/Feature/RET/ReturnQuantityTest` |
| BR-013 | Verified-purchase review requires an eligible delivered/completed order line owned by the reviewer. | REV | `review.not_eligible` guard + ownership check. | `tests/Feature/REV/ReviewEligibilityTest` |
| BR-014 | Promotion rules evaluated using version/timestamp effective at checkout. | PRI, CHK | Snapshot of promotion version into checkout/order. | `tests/Unit/PRI/PromotionVersioningTest` |
| BR-015 | Promotion stacking follows explicit precedence/exclusion; ambiguity rejected. | PRI | `pricing.promotion_conflict`; deterministic resolver. | `tests/Unit/PRI/StackingTest` |
| BR-016 | Historical orders not recalculated when config changes. | ORD | Immutable snapshots; no recompute path. | `tests/Feature/ORD/HistoricalImmutabilityTest` |
| BR-017 | All money uses fixed precision + documented rounding. | Support/Money, all | `Money` value object over integer minor units / DECIMAL(20,4); no float. | `tests/Unit/Money/MoneyTest` |
| BR-018 | order total = Σ line totals + tax + delivery + charges − allocated discounts/credits. | PRI, ORD | TotalsCalculator invariant + assertion. | `tests/Unit/PRI/TotalsTest` |
| BR-019 | Customer sees final payable total before confirming. | CHK | Final review step re-renders authoritative totals. | `tests/Feature/CHK/FinalReviewTest` |
| BR-020 | Payment event applied idempotently by provider event/txn reference + merchant context. | PAY | `payment_events` unique (provider, event_id); inbox dedup. | `tests/Feature/PAY/DuplicateCallbackTest` |
| BR-021 | COD collection reconciled to responsible shipment + collecting agent/provider. | PAY, SHP | `cod_collections` FK to shipment + agent. | `tests/Feature/PAY/CodReconcileTest` |
| BR-022 | Manual payment evidence can't mark order paid without authorized verification. | PAY | Manual verification requires `payments.verify` + approval. | `tests/Feature/PAY/ManualVerificationTest` |
| BR-023 | Sensitive card authentication data never stored. | PAY | No card/CVV/OTP columns; tokenized providers only; log redaction. | `tests/Security/PAY/NoCardDataTest` |
| BR-024 | A user cannot approve their own maker-checker action. | AUD, PAY, COM | `approval.self_approval_forbidden`; maker_id ≠ checker_id constraint. | `tests/Feature/AUD/MakerCheckerTest` |
| BR-025 | Suspended user/seller cannot start new protected transactions; retains history view. | IAM, SOB | Status guard in policies; read still permitted. | `tests/Feature/IAM/SuspendedUserTest` |
| BR-026 | Seller user always restricted by seller scope in addition to permission. | SEL, IAM | Scope resolver + query scoping; `seller.out_of_scope`. | `tests/Security/SEL/SellerScopeTest` |
| BR-027 | Seller payout-eligible only after approval + valid settlement info + reserve satisfied. | COM, SOB | Payout eligibility guard. | `tests/Feature/COM/PayoutEligibilityTest` |
| BR-028 | Commission snapshotted at order-item level when obligation created. | COM | `commission_snapshots` written at order confirm. | `tests/Feature/COM/CommissionSnapshotTest` |
| BR-029 | Seller payable balance excludes amounts pending delivery/return/dispute/hold. | COM | Ledger balance buckets (pending/available/reserved/payable). | `tests/Feature/COM/LedgerBucketsTest` |
| BR-030 | Payout ≤ eligible payable balance; not duplicated on retry. | COM | `payout.insufficient_available_balance` + idempotency key. | `tests/Feature/COM/PayoutIdempotencyTest` |
| BR-031 | Customer order may contain multiple seller sub-orders but one approved checkout calc. | MVO | Aggregate order + seller_orders allocation. | `tests/Feature/MVO/OrderSplitTest` |
| BR-032 | Seller cannot view another seller's sub-order/inventory/customer/ledger/performance. | SEL, MVO | Scope enforcement on every query. | `tests/Security/MVO/CrossSellerTest` |
| BR-033 | Customer PII to seller limited to fulfillment/return/support necessity. | SEL | Minimized seller customer resource. | `tests/Feature/SEL/PiiMinimizationTest` |
| BR-034 | Seller bank-account changes require reverification + approval before payout use. | SOB, COM | `seller.bank_verification_required` + maker-checker. | `tests/Feature/SOB/BankChangeTest` |
| BR-035 | Product publication may require moderation even for approved sellers. | PRD, SEL | `seller.offer_requires_review`. | `tests/Feature/SEL/OfferModerationTest` |
| BR-036 | Restricted products require category eligibility, documentation, delivery controls. | PRD, CHK | Policy metadata gates + checkout eligibility. | `tests/Feature/PRD/RestrictedProductTest` |
| BR-037 | Recalled/quarantined/expired inventory not available to sell/fulfill. | INV, RET | ATS excludes quarantined/recalled dispositions. | `tests/Feature/INV/QuarantineTest` |
| BR-038 | Return eligibility uses policy version on the order item unless law more favorable. | RET | Snapshotted policy version. | `tests/Feature/RET/PolicyVersionTest` |
| BR-039 | Refund decisions preserve evidence, reason, actor, approval, provider outcome. | PAY, RET | `refunds` + `refund_events` evidence columns. | `tests/Feature/RET/RefundEvidenceTest` |
| BR-040 | Failed notification never reverses a completed commerce transaction. | NTF | After-commit queued dispatch; failure isolation. | `tests/Feature/NTF/FailureIsolationTest` |
| BR-041 | Transactional comms sent as needed; marketing follows recorded consent + lawful basis. | NTF, MKT, CUS | Consent gate on marketing channel only. | `tests/Feature/NTF/ConsentGateTest` |
| BR-042 | Account deletion subject to verification, legal retention, fraud, open obligations. | AUD, CUS | Privacy deletion workflow with holds. | `tests/Feature/AUD/DeletionHoldTest` |
| BR-043 | Records under legal/dispute/security/privacy hold not deleted by retention jobs. | AUD | `legal_holds` checked by purge jobs. | `tests/Feature/AUD/LegalHoldTest` |
| BR-044 | Exports with restricted data require explicit authorization + audit. | DIE, RPT | Export authorization + audit event. | `tests/Security/DIE/ExportAuthTest` |
| BR-045 | Timestamps stored UTC; rendered per approved locale/timezone. | LOC, all | `Clock` + UTC TIMESTAMP(6); presentation-only calendar. | `tests/Unit/LOC/CalendarTest` |
| BR-046 | Public references non-sequential; internal keys not used for authz. | Support/Uuid, all | UUIDv7 public_id; route-model binding on public_id. | `tests/Architecture/PublicIdTest` |
| BR-047 | Duplicate external requests safe under documented idempotency contract. | INT, CHK, PAY | Idempotency records + fingerprint check. | `tests/Feature/CHK/IdempotencyTest` |
| BR-048 | Unsupported integration events retained for diagnosis; don't silently alter state. | INT | `inbox_events` stored as pending/unknown. | `tests/Feature/INT/UnknownEventTest` |
| BR-049 | Config referenced by history retired/versioned, not deleted. | ADM | `setting_versions`; deactivate not delete. | `tests/Feature/ADM/ConfigRetirementTest` |
| BR-050 | Critical production actions traceable via correlation ID. | Support/Correlation, all | Correlation middleware + propagation to jobs/events/audit. | `tests/Feature/AUD/CorrelationTest` |
