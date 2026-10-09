<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SpitsOnline\WeFact\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

// A test must never reach a real API.
beforeEach(fn () => Http::preventStrayRequests());

const HOSTFACT = 'https://hostfact.example.com/apiv2/api.php';
const WEFACT = 'https://api.mijnwefact.nl/v2/';

/**
 * An answer as HostFact sent it to hostfact-test, from tests/Fixtures.
 *
 * @return array<string, mixed>
 */
function apiFixture(string $name): array
{
    return json_decode((string) file_get_contents(__DIR__."/Fixtures/{$name}.json"), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * A success answer for `controller.action`, with the given body merged in.
 *
 * @param  array<string, mixed>  $body
 * @return array<string, mixed>
 */
function success(string $call, array $body = []): array
{
    [$controller, $action] = explode('.', $call);

    return ['controller' => $controller, 'action' => $action, 'status' => 'success', 'date' => '2026-10-09T12:00:00+02:00'] + $body;
}

/**
 * An error answer, as WeFact and HostFact send it: with HTTP 200.
 *
 * @param  list<string>  $errors
 * @return array<string, mixed>
 */
function failure(string $call, array $errors): array
{
    [$controller, $action] = explode('.', $call);

    return ['controller' => $controller, 'action' => $action, 'status' => 'error', 'date' => '2026-10-09T12:00:00+02:00', 'errors' => $errors];
}

/**
 * Fake the API: every request is answered by the response for its `controller.action`.
 * A response can be a body, a list of bodies (answered in turn), or a closure.
 *
 * @param  array<string, mixed>  $responses
 */
function fakeApi(array $responses): void
{
    $queues = array_map(fn (mixed $response) => is_array($response) && array_is_list($response) ? $response : [$response], $responses);

    Http::fake(function (Request $request) use (&$queues) {
        $call = "{$request['controller']}.{$request['action']}";

        if (! isset($queues[$call])) {
            return Http::response(failure($call, ['Invalid action']));
        }

        $response = count($queues[$call]) > 1 ? array_shift($queues[$call]) : $queues[$call][0];
        $response = $response instanceof Closure ? $response($request) : $response;

        return is_array($response) ? Http::response($response) : $response;
    });
}

/**
 * The parameters as the API receives them: form-encoded, so every value is a string.
 *
 * @return array<array-key, mixed>
 */
function wire(Request $request): array
{
    parse_str(http_build_query($request->data()), $parameters);

    return $parameters;
}

/**
 * Whether a request for `controller.action` was sent with these parameters, compared
 * as the API receives them (`'Identifier' => '4'`).
 *
 * @param  array<string, mixed>  $parameters
 */
function assertCalled(string $call, array $parameters = []): void
{
    [$controller, $action] = explode('.', $call);

    Http::assertSent(function (Request $request) use ($controller, $action, $parameters) {
        if ($request['controller'] !== $controller || $request['action'] !== $action) {
            return false;
        }

        foreach ($parameters as $key => $value) {
            if (data_get(wire($request), $key) !== $value) {
                return false;
            }
        }

        return true;
    });
}
