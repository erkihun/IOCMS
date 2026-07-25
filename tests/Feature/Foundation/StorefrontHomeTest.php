<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;

/**
 * Storefront home renders the Inertia Welcome page with typed props and a
 * correlation-id header (BR-050). Uses Inertia test assertions so it does not
 * depend on a built Vite manifest.
 */
it('renders the storefront home as an Inertia page', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertHeader('X-Correlation-Id');
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Welcome')
        ->has('stats')
        ->has('app')
        ->where('app.currency', 'ETB')
    );
});

it('applies safe security headers to every response', function () {
    $response = $this->get('/');

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('Content-Security-Policy');
});
