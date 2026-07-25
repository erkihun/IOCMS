<?php

declare(strict_types=1);

namespace App\Domain\Order\Actions;

use App\Domain\Governance\Services\AuditRecorder;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Platform\Integration\Services\OutboxRecorder;
use App\Support\Correlation\CorrelationContext;
use App\Support\Exceptions\BusinessRuleException;
use App\Support\Exceptions\ConflictException;
use Illuminate\Support\Facades\DB;

/**
 * Transitions an order through its lifecycle (FR-ORD-002, SRS §10.2.2, BR-003).
 * Rejects invalid transitions (order.invalid_transition) and stale versions
 * (order.concurrent_update). Records an append-only status event, audit and
 * outbox. Never called from controllers directly (SDD §10.6) — invoked by the
 * owning module Actions in response to committed events.
 */
final class TransitionOrderStatusAction
{
    public function __construct(
        private readonly OutboxRecorder $outbox,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(Order $order, OrderStatus $to, int $expectedLockVersion, string $reason): Order
    {
        return DB::transaction(function () use ($order, $to, $expectedLockVersion, $reason): Order {
            /** @var Order $fresh */
            $fresh = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($fresh->lock_version !== $expectedLockVersion) {
                throw new ConflictException('order.concurrent_update', 'Order changed during processing.');
            }

            $from = $fresh->status;
            if ($from === $to) {
                return $fresh; // idempotent no-op
            }
            if (! $from->canTransitionTo($to)) {
                throw new BusinessRuleException('order.invalid_transition', "Cannot move order from {$from->value} to {$to->value}.");
            }

            $fresh->status = $to;
            $fresh->lock_version = $expectedLockVersion + 1;
            if ($to === OrderStatus::Confirmed) {
                $fresh->confirmed_at = now()->toImmutable();
            }
            $fresh->save();

            DB::table('order_status_events')->insert([
                'order_id' => $fresh->id,
                'from_status' => $from->value,
                'to_status' => $to->value,
                'reason' => $reason,
                'correlation_id' => CorrelationContext::id(),
                'created_at' => now(),
            ]);

            $this->audit->record(
                action: 'order.status_changed',
                resourceType: 'order',
                resourcePublicId: $fresh->public_id,
                before: ['status' => $from->value],
                after: ['status' => $to->value],
            );

            $this->outbox->record(
                eventName: 'OrderStatusChanged',
                aggregateType: 'order',
                aggregatePublicId: $fresh->public_id,
                payload: ['from' => $from->value, 'to' => $to->value, 'reason' => $reason],
                aggregateVersion: $fresh->lock_version,
            );

            return $fresh;
        });
    }
}
