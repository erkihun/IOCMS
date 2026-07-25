<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Pest bootstrap (IOCMS)
|--------------------------------------------------------------------------
| Binds the Laravel TestCase to Feature/Integration/Security suites and
| RefreshDatabase so each test runs against a clean schema on the SQLite
| in-memory connection (see docs/implementation/decision-register.md D-002).
*/

uses(TestCase::class, RefreshDatabase::class)->in('Feature', 'Integration', 'Security');
uses(TestCase::class)->in('Architecture');

/*
|--------------------------------------------------------------------------
| Shared expectations & helpers
|--------------------------------------------------------------------------
*/

/**
 * Assert a value is a valid UUID (public identifier).
 */
expect()->extend('toBeUuid', function () {
    expect((bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string) $this->value))
        ->toBeTrue();

    return $this;
});

/**
 * Skip a test on non-MySQL drivers with an explicit, honest reason
 * (never a silent false-green). Used for real row-lock/oversell tests.
 */
function requiresMysql(): void
{
    if (DB::connection()->getDriverName() !== 'mysql') {
        test()->markTestSkipped('Requires MySQL for real row-level locking (see decision D-002).');
    }
}
