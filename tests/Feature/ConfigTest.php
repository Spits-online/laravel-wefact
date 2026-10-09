<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use SpitsOnline\WeFact\WeFactServiceProvider;

it('ships every default', function () {
    expect(require __DIR__.'/../../config/wefact.php')->toHaveKeys(['driver', 'url', 'key', 'timeout', 'timezone'])
        ->and(Config::get('wefact.timeout'))->toBe(10);
});

it('keeps the defaults an app does not override', function () {
    Config::set('wefact', ['key' => 'app-key']);

    (new WeFactServiceProvider(app()))->packageRegistered();

    expect(Config::get('wefact'))->toMatchArray([
        'key' => 'app-key',
        'driver' => 'wefact',
        'timeout' => 10,
    ]);
});
