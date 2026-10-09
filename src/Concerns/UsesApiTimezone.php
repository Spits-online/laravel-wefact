<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Concerns;

use Illuminate\Support\Facades\Config;

/**
 * The API sends and takes dates without an offset, in the timezone of the server it
 * runs on: `wefact.timezone`, or the app's timezone when that isn't set.
 */
trait UsesApiTimezone
{
    protected static function apiTimezone(): string
    {
        $timezone = Config::get('wefact.timezone');

        return is_string($timezone) && filled($timezone) ? $timezone : Config::string('app.timezone');
    }
}
