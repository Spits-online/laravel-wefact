<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Exceptions;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use SpitsOnline\WeFact\Enums\Driver;
use Throwable;

/**
 * The API refused the request. `$errors` holds its error messages, and `$body` the
 * whole answer. WeFact and HostFact answer errors with HTTP 200, so `$status` is
 * usually 200.
 */
class RequestFailed extends WeFactException
{
    /**
     * @param  array<int, string>  $errors
     * @param  array<array-key, mixed>  $body
     */
    final public function __construct(
        string $message,
        public readonly string $controller,
        public readonly string $action,
        public readonly array $errors = [],
        public readonly array $body = [],
        public readonly int $status = 200,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    /**
     * @param  array<int, string>  $errors
     * @param  array<array-key, mixed>  $body
     */
    public static function withErrors(Driver $driver, string $controller, string $action, array $errors, array $body = []): static
    {
        $reason = Arr::join($errors, ' ') ?: 'no reason given';

        return new static("{$driver->label()} refused `{$controller}.{$action}`: {$reason}", $controller, $action, $errors, $body);
    }

    public static function fromResponse(Driver $driver, Response $response, string $controller, string $action): static
    {
        return new static(
            message: "{$driver->label()} answered `{$controller}.{$action}` with HTTP {$response->status()} {$response->reason()}",
            controller: $controller,
            action: $action,
            body: (array) ($response->json() ?? []),
            status: $response->status(),
            previous: $response->toException(),
        );
    }

    /**
     * The API answered with success, but without what the request needs.
     *
     * @param  array<array-key, mixed>  $body
     */
    public static function unexpected(Driver $driver, string $controller, string $action, string $missing, array $body = []): static
    {
        return new static("{$driver->label()} answered `{$controller}.{$action}` without {$missing}.", $controller, $action, body: $body);
    }
}
