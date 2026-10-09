<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use SpitsOnline\WeFact\Enums\Driver;
use SpitsOnline\WeFact\Exceptions\AccessDenied;
use SpitsOnline\WeFact\Exceptions\ConnectionFailed;
use SpitsOnline\WeFact\Exceptions\MissingConfiguration;
use SpitsOnline\WeFact\Exceptions\NotFound;
use SpitsOnline\WeFact\Exceptions\RequestFailed;
use SpitsOnline\WeFact\Exceptions\UnsupportedFeature;
use SpitsOnline\WeFact\Exceptions\WeFactException;
use SpitsOnline\WeFact\Facades\WeFact;
use SpitsOnline\WeFact\WeFact as WeFactClient;

function client(array $config = []): WeFactClient
{
    return WeFactClient::fromConfig($config + ['driver' => 'hostfact', 'url' => HOSTFACT, 'key' => 'secret-key']);
}

it('posts the api key, controller and action as a form', function () {
    fakeApi(['debtor.list' => success('debtor.list', ['totalresults' => 0, 'debtors' => []])]);

    WeFact::request('debtor', 'list', ['searchfor' => 'Acme']);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === HOSTFACT
        && $request->isForm()
        && wire($request) === ['api_key' => 'secret-key', 'controller' => 'debtor', 'action' => 'list', 'searchfor' => 'Acme']);
});

it('returns the answer as the api sent it', function () {
    fakeApi(['debtor.show' => apiFixture('debtor')]);

    expect(WeFact::request('debtor', 'show', ['Identifier' => 4]))->toBe(apiFixture('debtor'));
});

it('never lets parameters replace the key, controller or action', function () {
    fakeApi(['debtor.list' => success('debtor.list')]);

    WeFact::request('debtor', 'list', ['api_key' => 'other', 'controller' => 'invoice']);

    assertCalled('debtor.list', ['api_key' => 'secret-key']);
});

it('talks to the wefact api when the wefact driver has no url', function () {
    Http::fake([WEFACT => Http::response(success('debtor.list'))]);

    client(['driver' => 'wefact', 'url' => null])->request('debtor', 'list');

    Http::assertSent(fn (Request $request) => $request->url() === WEFACT);
});

it('reads the driver from a string in any case or from an enum', function (mixed $driver, Driver $expected) {
    expect(client(['driver' => $driver])->driver())->toBe($expected);
})->with([
    ['wefact', Driver::WEFACT],
    ['HostFact', Driver::HOSTFACT],
    [Driver::HOSTFACT, Driver::HOSTFACT],
]);

it('defaults to the wefact driver', function () {
    expect(WeFactClient::fromConfig(['key' => 'k'])->driver())->toBe(Driver::WEFACT);
});

it('refuses a driver it does not know', function () {
    client(['driver' => 'exact']);
})->throws(MissingConfiguration::class, 'The `wefact.driver` config value `exact` is not a driver. Set `WEFACT_DRIVER` to `wefact` or `hostfact`.');

it('asks for the url on the hostfact driver', function () {
    client(['url' => null])->request('debtor', 'list');
})->throws(MissingConfiguration::class, 'The `wefact.url` config value is not set. Add `WEFACT_URL` to your .env file.');

it('asks for the api key', function () {
    client(['key' => ''])->request('debtor', 'list');
})->throws(MissingConfiguration::class, 'The `wefact.key` config value is not set. Add `WEFACT_KEY` to your .env file.');

it('throws access denied when the ip address is not whitelisted', function () {
    fakeApi(['debtor.list' => failure('invalid.invalid', ['IP 203.0.113.10 has no access to API'])]);

    try {
        WeFact::request('debtor', 'list');
        test()->fail('No exception was thrown.');
    } catch (AccessDenied $e) {
        expect($e->getMessage())->toBe("HostFact refused access: IP 203.0.113.10 has no access to API. Check `WEFACT_KEY`, and add this server's IP address under Instellingen → HostFact voorkeuren → API.")
            ->and($e->errors)->toBe(['IP 203.0.113.10 has no access to API'])
            ->and($e->controller)->toBe('debtor')
            ->and($e->action)->toBe('list');
    }
});

it('throws access denied for a wrong api key', function () {
    fakeApi(['debtor.list' => failure('invalid.invalid', ['API key is invalid'])]);

    WeFact::request('debtor', 'list');
})->throws(AccessDenied::class, 'HostFact refused access: API key is invalid.');

it('throws access denied when wefact has blocked the ip address', function () {
    Http::fake([WEFACT => Http::response('IP 203.0.113.10 currently in firewall', 403)]);

    client(['driver' => 'wefact', 'url' => null])->request('debtor', 'list');
})->throws(AccessDenied::class, 'WeFact refused access: IP 203.0.113.10 currently in firewall. Check `WEFACT_KEY`, and add this server\'s IP address under Instellingen → API.');

it('throws request failed with the errors and the answer', function () {
    fakeApi(['pricequote.add' => $body = failure('pricequote.add', ['Ongeldig kenmerk voor debiteur'])]);

    try {
        WeFact::request('pricequote', 'add');
        test()->fail('No exception was thrown.');
    } catch (RequestFailed $e) {
        expect($e)->not->toBeInstanceOf(NotFound::class)
            ->and($e->getMessage())->toBe('HostFact refused `pricequote.add`: Ongeldig kenmerk voor debiteur')
            ->and($e->errors)->toBe(['Ongeldig kenmerk voor debiteur'])
            ->and($e->body)->toBe($body)
            ->and($e->status)->toBe(200);
    }
});

it('throws not found when a show fails', function () {
    fakeApi(['debtor.show' => failure('debtor.show', ['Ongeldig kenmerk voor debiteur'])]);

    WeFact::request('debtor', 'show', ['Identifier' => 999]);
})->throws(NotFound::class, 'HostFact refused `debtor.show`: Ongeldig kenmerk voor debiteur');

it('throws request failed for an http error', function () {
    fakeApi(['debtor.list' => Http::response('Server error', 500)]);

    try {
        WeFact::request('debtor', 'list');
        test()->fail('No exception was thrown.');
    } catch (RequestFailed $e) {
        expect($e->status)->toBe(500)
            ->and($e->getMessage())->toBe('HostFact answered `debtor.list` with HTTP 500 Internal Server Error')
            ->and($e->getPrevious())->not->toBeNull();
    }
});

it('throws request failed for an answer that is not json', function () {
    fakeApi(['debtor.list' => Http::response('<html>Maintenance</html>')]);

    WeFact::request('debtor', 'list');
})->throws(RequestFailed::class, 'HostFact answered `debtor.list` without a JSON body.');

it('throws connection failed when the api cannot be reached', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

    WeFact::request('debtor', 'list');
})->throws(ConnectionFailed::class, 'Could not connect to the HostFact API: cURL error 28: Operation timed out');

it('lets one catch handle every package exception', function () {
    fakeApi(['debtor.list' => failure('invalid.invalid', ['API key is invalid'])]);

    expect(fn () => WeFact::request('debtor', 'list'))->toThrow(WeFactException::class);
});

it('has domains on the hostfact driver only', function () {
    Config::set('wefact.driver', 'wefact');
    app()->forgetInstance(WeFactClient::class);
    WeFact::clearResolvedInstances();

    WeFact::domains();
})->throws(UnsupportedFeature::class, 'WeFact has no domains; only HostFact does. Set `WEFACT_DRIVER=hostfact` to use them.');

it('resolves one client from the container', function () {
    expect(app(WeFactClient::class))->toBe(app(WeFactClient::class))
        ->and(WeFact::driver())->toBe(Driver::HOSTFACT);
});
