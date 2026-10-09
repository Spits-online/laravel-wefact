<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Exceptions;

final class MissingConfiguration extends WeFactException
{
    public static function key(string $key, string $env): self
    {
        return new self("The `wefact.{$key}` config value is not set. Add `{$env}` to your .env file.");
    }

    public static function driver(mixed $value): self
    {
        $value = is_scalar($value) ? (string) $value : get_debug_type($value);

        return new self("The `wefact.driver` config value `{$value}` is not a driver. Set `WEFACT_DRIVER` to `wefact` or `hostfact`.");
    }
}
