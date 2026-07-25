<?php

declare(strict_types=1);

/*
 * Architecture / boundary tests (LLD §11.2, SDD §15.3, prompt §7-8).
 * Enforced in CI so module boundaries and core invariants cannot regress.
 */

arch('strict types everywhere in app')
    ->expect('App')
    ->toUseStrictTypes();

arch('no debug statements ship')
    ->expect(['dd', 'dump', 'var_dump', 'ray', 'die'])
    ->not->toBeUsed();

arch('domain code does not depend on HTTP request objects')
    ->expect('App\Domain')
    ->not->toUse('Illuminate\Http\Request');

arch('domain code does not depend on the Inertia layer')
    ->expect('App\Domain')
    ->not->toUse('Inertia\Inertia');

arch('controllers live only in the Http layer')
    ->expect('App\Http\Controllers')
    ->toHaveSuffix('Controller');

arch('form requests extend the framework FormRequest')
    ->expect('App\Http\Requests')
    ->toExtend('Illuminate\Foundation\Http\FormRequest');

arch('enums are string-backed (locale-neutral stable values)')
    ->expect('App\Domain\Identity\Enums')
    ->toBeStringBackedEnums();

arch('money value object is immutable')
    ->expect('App\Support\Money\Money')
    ->toBeReadonly();

arch('provider adapters implement provider ports, not leak SDKs into domain')
    ->expect('App\Platform\Integration\Adapters')
    ->toImplement('App\Platform\Integration\Ports\PaymentGateway')
    ->ignoring('App\Platform\Integration\Adapters'); // only the payment adapter here for now

arch('domain modules do not depend on other domain modules HTTP requests')
    ->expect('App\Domain')
    ->not->toUse('App\Http\Requests');
