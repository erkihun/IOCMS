<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| IOCMS platform configuration
|--------------------------------------------------------------------------
| Typed, governed platform defaults (FR-ADM-001..003). Commercially, legally
| or security-significant values are versioned in the `system_settings` table
| at runtime; this file holds bootstrap defaults and non-secret constants only.
| Secrets are NEVER stored here — they are secret-manager/.env references
| (NFR-SE-016, decision D-008).
*/

return [
    'locales' => [
        'default' => env('IOCMS_DEFAULT_LOCALE', 'en'),
        'enabled' => ['en', 'am'],
    ],

    'currency' => [
        'default' => env('IOCMS_DEFAULT_CURRENCY', 'ETB'),
        // Presentation scale per currency (storage scale is always 4, LLD §3.5).
        'display_scale' => ['ETB' => 2, 'USD' => 2],
    ],

    'timezone' => [
        // Canonical storage is always UTC; this is the business display timezone.
        'business' => env('IOCMS_BUSINESS_TIMEZONE', 'Africa/Addis_Ababa'),
    ],

    'calendar' => [
        // Dual-calendar presentation (FR-LOC-004): Amharic → Ethiopian, English → Gregorian.
        'am' => 'ethiopian',
        'en' => 'gregorian',
    ],

    'reservations' => [
        // Stock reservation expiry window in minutes (FR-CHK-007, FR-INV-004). Configurable.
        'ttl_minutes' => (int) env('IOCMS_RESERVATION_TTL_MINUTES', 30),
    ],

    'features' => [
        // Master gate for the entire marketplace phase (SDD ADR-020, decision D-005).
        // Kept OFF until legal/tax/payment/settlement/support/operational readiness
        // is explicitly configured and governance approves.
        'marketplace' => (bool) env('IOCMS_FEATURE_MARKETPLACE', false),
    ],
];
