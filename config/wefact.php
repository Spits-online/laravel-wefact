<?php

declare(strict_types=1);

/*
 * You never need to publish this file: set the env keys below. Publish it only to
 * read the defaults, and keep just the keys you change.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Driver
    |--------------------------------------------------------------------------
    |
    | Which API to talk to: `wefact` (the online invoicing service) or
    | `hostfact` (the self-hosted invoicing software for hosting companies).
    | Both speak the same API, so switching from HostFact to WeFact is a
    | matter of changing this key, the URL and the API key. Domains only
    | exist in HostFact. A `SpitsOnline\WeFact\Enums\Driver` case works too.
    |
    */

    'driver' => env('WEFACT_DRIVER', 'wefact'),

    /*
    |--------------------------------------------------------------------------
    | API
    |--------------------------------------------------------------------------
    |
    | WeFact's API lives at https://api.mijnwefact.nl/v2/, which is used when
    | the URL is left empty. HostFact runs on its own server, so its URL is
    | required: the `apiv2/api.php` under the HostFact address, e.g.
    | https://administratie.example.com/apiv2/api.php.
    |
    | The key is the "beveiligingscode" under Instellingen → API (WeFact) or
    | Instellingen → HostFact voorkeuren → API (HostFact). Add the IP address
    | of every server that calls the API to the whitelist on that same page,
    | or each request is refused.
    |
    */

    'url' => env('WEFACT_URL'),
    'key' => env('WEFACT_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | Seconds to wait for an answer before giving up. Requests usually run
    | inside a web request, so an API that hangs should fail fast rather than
    | hold the page.
    |
    */

    'timeout' => env('WEFACT_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Timezone
    |--------------------------------------------------------------------------
    |
    | The API sends dates without an offset (`2026-05-01 12:00:00`), in the
    | timezone of the server it runs on. They are read, and dates you send
    | are written, in this timezone, or in the app's timezone when it isn't
    | set.
    |
    */

    'timezone' => env('WEFACT_TIMEZONE'),

];
