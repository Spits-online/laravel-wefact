<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
use SpitsOnline\WeFact\Enums\Driver;
use SpitsOnline\WeFact\Exceptions\AccessDenied;
use SpitsOnline\WeFact\Exceptions\ConnectionFailed;
use SpitsOnline\WeFact\Exceptions\MissingConfiguration;
use SpitsOnline\WeFact\Exceptions\NotFound;
use SpitsOnline\WeFact\Exceptions\RequestFailed;
use SpitsOnline\WeFact\Exceptions\UnsupportedFeature;
use SpitsOnline\WeFact\Resources\DebtorResource;
use SpitsOnline\WeFact\Resources\Debtors;
use SpitsOnline\WeFact\Resources\Domains;
use SpitsOnline\WeFact\Resources\InvoiceResource;
use SpitsOnline\WeFact\Resources\Invoices;
use SpitsOnline\WeFact\Resources\Products;
use SpitsOnline\WeFact\Resources\QuoteResource;
use SpitsOnline\WeFact\Resources\Quotes;
use SpitsOnline\WeFact\Resources\Subscriptions;

class WeFact
{
    public function __construct(
        protected Driver $driver,
        protected ?string $url,
        protected ?string $key,
        protected int $timeout = 10,
    ) {}

    /**
     * @param  array<array-key, mixed>  $config
     *
     * @internal
     */
    public static function fromConfig(array $config): self
    {
        $data = new Fluent($config);
        $driver = $data->get('driver', Driver::WEFACT->value);
        $driver = $driver instanceof Driver ? $driver : (is_string($driver) ? Driver::tryFrom(Str::lower($driver)) : null);

        return new self(
            driver: $driver ?? throw MissingConfiguration::driver($data->get('driver')),
            url: $data->string('url')->value() ?: null,
            key: $data->string('key')->value() ?: null,
            timeout: $data->integer('timeout', 10) ?: 10,
        );
    }

    /**
     * The API this client talks to.
     */
    public function driver(): Driver
    {
        return $this->driver;
    }

    /**
     * Every debtor, and creating new ones.
     */
    public function debtors(): Debtors
    {
        return new Debtors($this);
    }

    /**
     * One debtor, by its id. Sends no request until you call a method on it. Only
     * have the debtor code? `debtors()->findByCode('DB10001')` looks it up.
     */
    public function debtor(int $id): DebtorResource
    {
        return new DebtorResource($this, $id);
    }

    /**
     * Every quote, and creating new ones.
     */
    public function quotes(): Quotes
    {
        return new Quotes($this);
    }

    /**
     * One quote, by its id. Sends no request until you call a method on it.
     */
    public function quote(int $id): QuoteResource
    {
        return new QuoteResource($this, $id);
    }

    /**
     * Every invoice, and creating new ones.
     */
    public function invoices(): Invoices
    {
        return new Invoices($this);
    }

    /**
     * One invoice, by its id. Sends no request until you call a method on it.
     */
    public function invoice(int $id): InvoiceResource
    {
        return new InvoiceResource($this, $id);
    }

    /**
     * The product catalogue.
     */
    public function products(): Products
    {
        return new Products($this);
    }

    /**
     * Every subscription, including the ones attached to domains and hosting on HostFact.
     */
    public function subscriptions(): Subscriptions
    {
        return new Subscriptions($this);
    }

    /**
     * Every domain HostFact manages. Only the HostFact driver has domains.
     *
     * @throws UnsupportedFeature on the WeFact driver
     */
    public function domains(): Domains
    {
        if ($this->driver !== Driver::HOSTFACT) {
            throw UnsupportedFeature::for('domains', $this->driver, Driver::HOSTFACT);
        }

        return new Domains($this);
    }

    /**
     * Send a request and return the one record its answer holds under `$key`.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<array-key, mixed>
     *
     * @internal
     */
    public function record(string $controller, string $action, array $parameters, ?string $key = null): array
    {
        $key ??= $controller;
        $body = $this->request($controller, $action, $parameters);
        $record = Arr::get($body, $key);

        return is_array($record) ? $record : throw RequestFailed::unexpected($this->driver, $controller, $action, "a `{$key}`", $body);
    }

    /**
     * Send any request the API documents and return its answer as it was sent. For
     * what this package doesn't model, such as HostFact's hosting accounts or WeFact's
     * bank transactions: `WeFact::request('hosting', 'list', ['status' => 4])`.
     *
     * Throws the same exceptions as every other call: `AccessDenied`, `NotFound` (for
     * a `show` of something that doesn't exist), `RequestFailed` and `ConnectionFailed`.
     *
     * @param  array<string, mixed>  $parameters  in the API's own keys
     * @return array<array-key, mixed>
     */
    public function request(string $controller, string $action, array $parameters = []): array
    {
        $url = $this->url ?: $this->driver->defaultUrl() ?: throw MissingConfiguration::key('url', 'WEFACT_URL');
        $key = $this->key ?: throw MissingConfiguration::key('key', 'WEFACT_KEY');

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout($this->timeout)
                ->post($url, ['api_key' => $key, 'controller' => $controller, 'action' => $action] + $parameters);
        } catch (ConnectionException $e) {
            throw ConnectionFailed::from($this->driver, $e);
        }

        // WeFact blocks an IP address that goes over its rate limits with a 403.
        if ($response->forbidden()) {
            throw AccessDenied::withErrors($this->driver, $controller, $action, [Str::of($response->body())->stripTags()->trim()->value() ?: 'HTTP 403 Forbidden']);
        }

        if ($response->failed()) {
            throw RequestFailed::fromResponse($this->driver, $response, $controller, $action);
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw RequestFailed::unexpected($this->driver, $controller, $action, 'a JSON body');
        }

        if (Arr::get($body, 'status') === 'success') {
            return $body;
        }

        $errors = collect(Arr::wrap(Arr::get($body, 'errors')))->filter(fn (mixed $error) => is_string($error))->values()->all();

        // A refused key or IP address comes back for an "invalid" controller.
        if (Arr::get($body, 'controller') === 'invalid' && Str::contains(Arr::join($errors, ' '), ['API key', 'access to API', 'firewall'], ignoreCase: true)) {
            throw AccessDenied::withErrors($this->driver, $controller, $action, $errors, $body);
        }

        if ($action === 'show') {
            throw NotFound::withErrors($this->driver, $controller, $action, $errors, $body);
        }

        throw RequestFailed::withErrors($this->driver, $controller, $action, $errors, $body);
    }
}
