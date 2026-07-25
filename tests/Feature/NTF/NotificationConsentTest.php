<?php

declare(strict_types=1);

use App\Domain\Notification\Services\NotificationDispatcher;
use App\Support\Uuid\PublicId;
use Illuminate\Support\Facades\DB;

/**
 * FR-NTF-004/007, BR-041 — transactional vs consent-based marketing + duplicate
 * suppression.
 */
beforeEach(fn () => $this->dispatcher = app(NotificationDispatcher::class));

function seedUser(): int
{
    return (int) DB::table('users')->insertGetId(['public_id' => PublicId::generate(), 'type' => 'customer', 'status' => 'active', 'locale' => 'en', 'created_at' => now(), 'updated_at' => now()]);
}

it('always queues a transactional notification', function () {
    $user = seedUser();
    $result = $this->dispatcher->queue($user, 'order.placed', 'email', 'transactional');
    expect($result['status'])->toBe('queued');
});

it('suppresses a marketing notification without recorded consent (BR-041)', function () {
    $user = seedUser();
    $result = $this->dispatcher->queue($user, 'promo.sale', 'email', 'marketing', 'marketing_email');
    expect($result['status'])->toBe('suppressed');
});

it('queues a marketing notification when consent is granted', function () {
    $user = seedUser();
    DB::table('consent_records')->insert(['public_id' => PublicId::generate(), 'user_id' => $user, 'purpose' => 'marketing_email', 'granted' => true, 'recorded_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

    $result = $this->dispatcher->queue($user, 'promo.sale', 'email', 'marketing', 'marketing_email');
    expect($result['status'])->toBe('queued');
});

it('suppresses a duplicate notification for the same event+recipient+channel (FR-NTF-007)', function () {
    $user = seedUser();
    $this->dispatcher->queue($user, 'order.shipped', 'email', 'transactional');
    $second = $this->dispatcher->queue($user, 'order.shipped', 'email', 'transactional');

    expect($second['status'])->toBe('duplicate');
    expect(DB::table('notifications')->where('user_id', $user)->where('event_key', 'order.shipped')->count())->toBe(1);
});
