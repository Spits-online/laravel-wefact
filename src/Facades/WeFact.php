<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Facades;

use Illuminate\Support\Facades\Facade;
use SpitsOnline\WeFact\Enums\Driver;
use SpitsOnline\WeFact\Testing\WeFactFake;
use SpitsOnline\WeFact\WeFact as WeFactClient;

/**
 * @method static Driver driver()
 * @method static \SpitsOnline\WeFact\Resources\Debtors debtors()
 * @method static \SpitsOnline\WeFact\Resources\DebtorResource debtor(int $id)
 * @method static \SpitsOnline\WeFact\Resources\Quotes quotes()
 * @method static \SpitsOnline\WeFact\Resources\QuoteResource quote(int $id)
 * @method static \SpitsOnline\WeFact\Resources\Invoices invoices()
 * @method static \SpitsOnline\WeFact\Resources\InvoiceResource invoice(int $id)
 * @method static \SpitsOnline\WeFact\Resources\Products products()
 * @method static \SpitsOnline\WeFact\Resources\Subscriptions subscriptions()
 * @method static \SpitsOnline\WeFact\Resources\Domains domains()
 * @method static array<array-key, mixed> request(string $controller, string $action, array<string, mixed> $parameters = [])
 *
 * @see WeFactClient
 * @see WeFactFake
 */
final class WeFact extends Facade
{
    /**
     * Swap the client for an in-memory fake, for the configured driver unless you pass one.
     */
    public static function fake(?Driver $driver = null): WeFactFake
    {
        $root = self::getFacadeRoot();
        $driver ??= $root instanceof WeFactClient ? $root->driver() : Driver::WEFACT;

        self::swap($fake = new WeFactFake($driver));

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return WeFactClient::class;
    }
}
