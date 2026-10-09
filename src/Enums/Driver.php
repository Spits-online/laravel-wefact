<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Enums;

/**
 * Which API the package talks to. WeFact and HostFact share one API protocol (same
 * controllers, actions and answers), so every call works the same on both, except
 * where a method's docblock says otherwise.
 */
enum Driver: string
{
    /**
     * WeFact, the online invoicing service, at https://api.mijnwefact.nl/v2/.
     */
    case WEFACT = 'wefact';

    /**
     * HostFact, the invoicing software for hosting companies that runs on its own
     * server. It adds domains, and needs `WEFACT_URL` to point at the installation.
     */
    case HOSTFACT = 'hostfact';

    public function label(): string
    {
        return match ($this) {
            self::WEFACT => 'WeFact',
            self::HOSTFACT => 'HostFact',
        };
    }

    /**
     * The API address when `wefact.url` isn't set. HostFact has none: every
     * installation has its own.
     */
    public function defaultUrl(): ?string
    {
        return match ($this) {
            self::WEFACT => 'https://api.mijnwefact.nl/v2/',
            self::HOSTFACT => null,
        };
    }

    /**
     * Where the API key and the IP whitelist live in the admin.
     */
    public function settingsPath(): string
    {
        return match ($this) {
            self::WEFACT => 'Instellingen → API',
            self::HOSTFACT => 'Instellingen → HostFact voorkeuren → API',
        };
    }
}
