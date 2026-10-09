<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Exceptions;

use SpitsOnline\WeFact\Enums\Driver;

/**
 * The configured driver's API doesn't have this feature, e.g. domains on WeFact.
 */
final class UnsupportedFeature extends WeFactException
{
    public static function for(string $feature, Driver $driver, Driver $needs): self
    {
        return new self("{$driver->label()} has no {$feature}; only {$needs->label()} does. Set `WEFACT_DRIVER={$needs->value}` to use them.");
    }
}
