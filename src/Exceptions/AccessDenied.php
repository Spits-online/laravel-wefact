<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Exceptions;

use Illuminate\Support\Arr;
use SpitsOnline\WeFact\Enums\Driver;

/**
 * The API refused the API key, or the IP address of this server. WeFact also blocks
 * an IP address that goes over its rate limits (HTTP 403). Nothing about the request
 * itself was wrong, so don't treat this as a missing record.
 */
final class AccessDenied extends RequestFailed
{
    /**
     * @param  array<int, string>  $errors
     * @param  array<array-key, mixed>  $body
     */
    public static function withErrors(Driver $driver, string $controller, string $action, array $errors, array $body = []): static
    {
        return new self(
            "{$driver->label()} refused access: ".Arr::join($errors, ' ').". Check `WEFACT_KEY`, and add this server's IP address under {$driver->settingsPath()}.",
            $controller,
            $action,
            $errors,
            $body,
            $body === [] ? 403 : 200,
        );
    }
}
