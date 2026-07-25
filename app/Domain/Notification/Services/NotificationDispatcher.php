<?php

declare(strict_types=1);

namespace App\Domain\Notification\Services;

use App\Support\Clock\Clock;
use App\Support\Uuid\PublicId;
use Illuminate\Support\Facades\DB;

/**
 * Queues notifications with consent gating and duplicate suppression (FR-NTF-001/
 * 004/007, BR-040/041).
 *
 * - Transactional notifications (order/payment/shipment/...) are always allowed.
 * - Marketing notifications require a recorded, granted consent for the purpose
 *   (BR-041); without it the message is suppressed, not sent.
 * - A dedup key (event + recipient + channel) prevents duplicate sends (FR-NTF-007).
 * - Dispatch is recorded here and delivered asynchronously; a delivery failure
 *   never rolls back the originating commerce transaction (BR-040) because this
 *   runs after commit and swallows provider errors into a failed attempt record.
 */
final class NotificationDispatcher
{
    /**
     * @return array{status: string, notification_public_id: string|null}
     */
    public function queue(
        ?int $userId,
        string $eventKey,
        string $channel,
        string $category = 'transactional',
        ?string $marketingPurpose = null,
    ): array {
        // Marketing consent gate (BR-041).
        if ($category === 'marketing') {
            $consented = $userId !== null && DB::table('consent_records')
                ->where('user_id', $userId)
                ->where('purpose', $marketingPurpose ?? 'marketing_'.$channel)
                ->where('granted', true)
                ->orderByDesc('recorded_at')
                ->exists();

            if (! $consented) {
                return ['status' => 'suppressed', 'notification_public_id' => null];
            }
        }

        $dedupKey = hash('sha256', $eventKey.'|'.($userId ?? 'anon').'|'.$channel);

        // FR-NTF-007: suppress duplicate per event+recipient+channel.
        $publicId = PublicId::generate();
        $inserted = DB::table('notifications')->insertOrIgnore([
            'public_id' => $publicId,
            'user_id' => $userId,
            'event_key' => $eventKey,
            'channel' => $channel,
            'category' => $category,
            'status' => 'queued',
            'dedup_key' => $dedupKey,
            'created_at' => Clock::now(),
            'updated_at' => Clock::now(),
        ]);

        if ($inserted === 0) {
            return ['status' => 'duplicate', 'notification_public_id' => null];
        }

        return ['status' => 'queued', 'notification_public_id' => $publicId];
    }
}
