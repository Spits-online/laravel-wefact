<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Exceptions;

use Illuminate\Http\Client\ConnectionException;
use SpitsOnline\WeFact\Enums\Driver;

/**
 * The API could not be reached, or didn't answer within `wefact.timeout` seconds.
 */
final class ConnectionFailed extends WeFactException
{
    public static function from(Driver $driver, ConnectionException $exception): self
    {
        return new self("Could not connect to the {$driver->label()} API: {$exception->getMessage()}", previous: $exception);
    }
}
