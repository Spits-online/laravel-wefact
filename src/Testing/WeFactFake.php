<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Testing;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
use PHPUnit\Framework\Assert as PHPUnit;
use SpitsOnline\WeFact\Data\Debtor;
use SpitsOnline\WeFact\Data\Invoice;
use SpitsOnline\WeFact\Data\Line;
use SpitsOnline\WeFact\Data\LineItem;
use SpitsOnline\WeFact\Data\Quote;
use SpitsOnline\WeFact\Enums\Driver;
use SpitsOnline\WeFact\Exceptions\NotFound;
use SpitsOnline\WeFact\Exceptions\RequestFailed;
use SpitsOnline\WeFact\WeFact;

/**
 * An in-memory WeFact (or HostFact) for testing apps. Enable it with `WeFact::fake()`,
 * seed it with `withDebtor()`, `withProduct()` and the others, and assert on what changed.
 *
 * It answers the same requests the real client sends, so everything works on it the way
 * it works on the API: lists page and filter, a missing record answers `NotFound`, a
 * declined quote can't be accepted, only concept invoices can be deleted, and accepting
 * a quote with `createInvoice: true` creates a concept invoice. Seed records in the
 * API's own keys (`['CompanyName' => 'Acme']`); the fake fills in the rest.
 */
class WeFactFake extends WeFact
{
    /**
     * The tax rate of a line that doesn't name one.
     */
    public const float TAX_PERCENTAGE = 21;

    /**
     * The key that holds a record's code, per controller.
     */
    protected const array CODE_KEYS = [
        'debtor' => 'DebtorCode',
        'pricequote' => 'PriceQuoteCode',
        'invoice' => 'InvoiceCode',
        'product' => 'ProductCode',
    ];

    /**
     * The fields a `list` searches when `searchat` isn't given.
     */
    protected const array SEARCH_FIELDS = ['DebtorCode', 'PriceQuoteCode', 'InvoiceCode', 'ProductCode', 'CompanyName', 'SurName', 'ProductName', 'Domain'];

    /**
     * @var array<string, array<int, array<array-key, mixed>>>
     */
    protected array $records = [
        'debtor' => [],
        'pricequote' => [],
        'invoice' => [],
        'product' => [],
        'subscription' => [],
        'domain' => [],
    ];

    /** @var list<array{change: string, subject: ?int, data: mixed}> */
    protected array $changes = [];

    protected int $nextLineId = 1;

    public function __construct(Driver $driver = Driver::WEFACT)
    {
        parent::__construct($driver, 'https://wefact.test', 'fake');
    }

    /**
     * @param  array<array-key, mixed>  $attributes  in the API's keys, e.g. `['CompanyName' => 'Acme']`
     */
    public function withDebtor(array $attributes = []): static
    {
        $this->storeDebtor($attributes);

        return $this;
    }

    /**
     * @param  array<array-key, mixed>  $attributes  in the API's keys, e.g. `['ProductCode' => 'P001', 'PriceExcl' => 95]`
     */
    public function withProduct(array $attributes = []): static
    {
        $id = $this->nextId('product', $attributes);

        $this->records['product'][$id] = [
            'ProductCode' => 'P'.Str::padLeft((string) $id, 3, '0'),
            'ProductName' => "Product {$id}",
            'ProductKeyPhrase' => '',
            'ProductDescription' => '',
            'ProductType' => 'other',
            'NumberSuffix' => '',
            'PriceExcl' => '0',
            'PricePeriod' => '',
            'TaxPercentage' => self::TAX_PERCENTAGE,
            'Modified' => $this->now(),
            ...$attributes,
            'Identifier' => (string) $id,
        ];

        return $this;
    }

    /**
     * @param  array<array-key, mixed>  $attributes  in the API's keys; `Debtor` must be a seeded debtor
     * @param  list<Line>  $lines
     */
    public function withQuote(array $attributes = [], array $lines = []): static
    {
        $this->storeDocument('pricequote', $attributes, Arr::map($lines, fn (Line $line) => $line->toArray()));

        return $this;
    }

    /**
     * @param  array<array-key, mixed>  $attributes  in the API's keys; `Debtor` must be a seeded debtor
     * @param  list<Line>  $lines
     */
    public function withInvoice(array $attributes = [], array $lines = []): static
    {
        $this->storeDocument('invoice', $attributes, Arr::map($lines, fn (Line $line) => $line->toArray()));

        return $this;
    }

    /**
     * @param  array<array-key, mixed>  $attributes  in the API's keys; `Debtor` must be a seeded debtor
     */
    public function withSubscription(array $attributes = []): static
    {
        $id = $this->nextId('subscription', $attributes);

        $this->records['subscription'][$id] = [
            'ProductCode' => '',
            'Description' => '',
            'Number' => '1',
            'NumberSuffix' => '',
            'PriceExcl' => '0',
            'TaxPercentage' => self::TAX_PERCENTAGE,
            'DiscountPercentage' => 0,
            'Periods' => '1',
            'Periodic' => 'm',
            'NextDate' => '',
            'TerminationDate' => '',
            'PeriodicType' => 'other',
            'Status' => 'active',
            'Modified' => $this->now(),
            ...$attributes,
            ...$this->debtorFields($this->debtorFor($attributes, 'subscription', 'add')),
            'Identifier' => (string) $id,
        ];

        return $this;
    }

    /**
     * Only on the HostFact driver, which has domains.
     *
     * @param  array<array-key, mixed>  $attributes  in the API's keys, e.g. `['Domain' => 'example', 'Tld' => 'com']`
     */
    public function withDomain(array $attributes = []): static
    {
        $id = $this->nextId('domain', $attributes);

        $this->records['domain'][$id] = [
            'Domain' => "domain{$id}",
            'Tld' => 'com',
            'Status' => '4',
            'RegistrationDate' => '',
            'ExpirationDate' => '',
            'RegistrarName' => '',
            'Modified' => $this->now(),
            ...$attributes,
            ...$this->debtorFields($this->debtorFor($attributes, 'domain', 'add')),
            'Identifier' => (string) $id,
        ];

        return $this;
    }

    /**
     * @param  (callable(Debtor): bool)|null  $callback
     */
    public function assertDebtorCreated(?callable $callback = null): void
    {
        $this->assertChanged('debtor.created', null, $callback, 'No matching debtor was created.');
    }

    /**
     * @param  (callable(array<string, mixed> $changes): bool)|null  $callback  receives the changed fields in the API's keys, e.g. `['Comment' => '…']`
     */
    public function assertDebtorUpdated(int $id, ?callable $callback = null): void
    {
        $this->assertChanged('debtor.updated', $id, $callback, "Debtor {$id} was not updated.");
    }

    /**
     * @param  (callable(Quote): bool)|null  $callback
     */
    public function assertQuoteCreated(?callable $callback = null): void
    {
        $this->assertChanged('pricequote.created', null, $callback, 'No matching quote was created.');
    }

    /**
     * @param  (callable(array<string, mixed> $changes): bool)|null  $callback  receives the changed fields in the API's keys
     */
    public function assertQuoteUpdated(int $id, ?callable $callback = null): void
    {
        $this->assertChanged('pricequote.updated', $id, $callback, "Quote {$id} was not updated.");
    }

    public function assertQuoteAccepted(int $id): void
    {
        $this->assertChanged('pricequote.accepted', $id, null, "Quote {$id} was not accepted.");
    }

    public function assertQuoteDeclined(int $id): void
    {
        $this->assertChanged('pricequote.declined', $id, null, "Quote {$id} was not declined.");
    }

    public function assertQuoteArchived(int $id): void
    {
        $this->assertChanged('pricequote.archived', $id, null, "Quote {$id} was not archived.");
    }

    /**
     * @param  (callable(array<int, LineItem>): bool)|null  $callback  receives the lines added in one call
     */
    public function assertQuoteLinesAdded(int $quote, ?callable $callback = null): void
    {
        $this->assertChanged('pricequote.lines-added', $quote, $callback, "No matching lines were added to quote {$quote}.");
    }

    /**
     * @param  (callable(array<int, int>): bool)|null  $callback  receives the ids of the lines removed in one call
     */
    public function assertQuoteLinesRemoved(int $quote, ?callable $callback = null): void
    {
        $this->assertChanged('pricequote.lines-removed', $quote, $callback, "No matching lines were removed from quote {$quote}.");
    }

    /**
     * @param  (callable(Invoice): bool)|null  $callback
     */
    public function assertInvoiceCreated(?callable $callback = null): void
    {
        $this->assertChanged('invoice.created', null, $callback, 'No matching invoice was created.');
    }

    public function assertInvoiceDeleted(int $id): void
    {
        $this->assertChanged('invoice.deleted', $id, null, "Invoice {$id} was not deleted.");
    }

    /**
     * @param  (callable(array<int, LineItem>): bool)|null  $callback  receives the lines added in one call
     */
    public function assertInvoiceLinesAdded(int $invoice, ?callable $callback = null): void
    {
        $this->assertChanged('invoice.lines-added', $invoice, $callback, "No matching lines were added to invoice {$invoice}.");
    }

    /**
     * @param  (callable(array<int, int>): bool)|null  $callback  receives the ids of the lines removed in one call
     */
    public function assertInvoiceLinesRemoved(int $invoice, ?callable $callback = null): void
    {
        $this->assertChanged('invoice.lines-removed', $invoice, $callback, "No matching lines were removed from invoice {$invoice}.");
    }

    public function assertNothingChanged(): void
    {
        $count = count($this->changes);

        PHPUnit::assertSame(0, $count, "Expected no changes, but {$count} were made.");
    }

    /**
     * Answer a request the way the API would, from the seeded records.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<array-key, mixed>
     *
     * @internal
     */
    public function request(string $controller, string $action, array $parameters = []): array
    {
        $input = new Fluent($parameters);

        $result = match ("{$controller}.{$action}") {
            'debtor.list', 'pricequote.list', 'invoice.list', 'product.list', 'subscription.list', 'domain.list' => $this->list($controller, $input),
            'debtor.show', 'pricequote.show', 'invoice.show', 'product.show', 'domain.show' => [$controller => $this->present($controller, $this->find($controller, $action, $input))],
            'debtor.add' => ['debtor' => $this->createDebtor($input)],
            'debtor.edit', 'pricequote.edit' => [$controller => $this->edit($controller, $input)],
            'pricequote.add', 'invoice.add' => [$controller => $this->createDocument($controller, $input)],
            'pricequote.accept' => ['pricequote' => $this->accept($input)],
            'pricequote.decline' => ['pricequote' => $this->decline($input)],
            'pricequote.archive', 'pricequote.delete' => $this->archive($action, $input),
            'invoice.delete' => $this->deleteInvoice($input),
            'pricequoteline.add', 'invoiceline.add' => $this->addLines(Str::before($controller, 'line'), $input),
            'pricequoteline.delete', 'invoiceline.delete' => $this->removeLines(Str::before($controller, 'line'), $input),
            default => throw $this->fail($controller, $action, 'Invalid action'),
        };

        return ['controller' => $controller, 'action' => $action, 'status' => 'success', 'date' => Carbon::now()->toIso8601String(), ...$result];
    }

    /**
     * @param  Fluent<array-key, mixed>  $parameters
     * @return array<array-key, mixed>
     */
    protected function list(string $controller, Fluent $parameters): array
    {
        // Like the API, subscriptions are filtered on `active` unless a status is given.
        $statuses = ($controller === 'subscription' && ! $parameters->has('status') ? Str::of('active') : $parameters->string('status'))
            ->explode('|')
            ->filter(fn (string $status) => filled($status));
        $searchFor = $parameters->string('searchfor')->value();
        $searchAt = $parameters->string('searchat')->explode('|')->filter(fn (string $field) => filled($field));
        $modifiedFrom = $parameters->string('modified.from')->value();

        $rows = collect($this->records[$controller])
            ->reject(fn (array $row) => $controller === 'pricequote' && Arr::get($row, 'Archived') === true)
            ->filter(fn (array $row) => $statuses->isEmpty() || $statuses->containsStrict(self::text(Arr::get($row, 'Status'))))
            ->filter(fn (array $row) => blank($modifiedFrom) || self::text(Arr::get($row, 'Modified')) >= $modifiedFrom)
            ->filter(fn (array $row) => blank($searchFor) || $this->matches($row, $searchAt, $searchFor))
            ->values();

        $offset = $parameters->integer('offset');
        $page = $rows->slice($offset, $parameters->integer('limit') ?: 1000)->values();

        return [
            'totalresults' => $offset >= $rows->count() ? 0 : $rows->count(),
            'currentresults' => $page->count(),
            'offset' => $offset,
            Str::plural($controller) => $page->map(fn (array $row) => Arr::except($this->present($controller, $row), ['PriceQuoteLines', 'InvoiceLines']))->all(),
        ];
    }

    /**
     * Searching the `Debtor` field matches the debtor id exactly; every other field
     * matches on part of its text, like the API.
     *
     * @param  array<array-key, mixed>  $row
     * @param  Collection<int, string>  $searchAt
     */
    protected function matches(array $row, Collection $searchAt, string $searchFor): bool
    {
        if ($searchAt->all() === ['Debtor']) {
            return self::text(Arr::get($row, 'Debtor')) === $searchFor;
        }

        return ($searchAt->isEmpty() ? collect(self::SEARCH_FIELDS) : $searchAt)
            ->contains(fn (string $field) => Str::contains(self::text(Arr::get($row, $field)), $searchFor, ignoreCase: true));
    }

    /**
     * @param  Fluent<array-key, mixed>  $parameters
     * @return array<array-key, mixed>
     */
    protected function find(string $controller, string $action, Fluent $parameters): array
    {
        $codeKey = self::CODE_KEYS[$controller] ?? null;

        $record = collect($this->records[$controller])->first(fn (array $row, int $id) => ($parameters->filled('Identifier') && $parameters->integer('Identifier') === $id)
            || ($codeKey !== null && $parameters->filled($codeKey) && $parameters->string($codeKey)->value() === Arr::get($row, $codeKey)));

        return is_array($record) ? $record : throw $this->fail($controller, $action, "Invalid identifier for {$controller}");
    }

    /**
     * @param  Fluent<array-key, mixed>  $parameters
     * @return array<array-key, mixed>
     */
    protected function createDebtor(Fluent $parameters): array
    {
        $debtor = $this->storeDebtor($parameters->toArray());
        $this->remember('debtor.created', null, $debtor);

        return $debtor;
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     * @return array<array-key, mixed>
     */
    protected function storeDebtor(array $attributes): array
    {
        $id = $this->nextId('debtor', $attributes);

        return $this->records['debtor'][$id] = [
            'DebtorCode' => 'DB'.(10000 + $id),
            'CompanyName' => '',
            'Initials' => '',
            'SurName' => '',
            'EmailAddress' => '',
            'Comment' => '',
            'Created' => $this->now(),
            'Modified' => $this->now(),
            ...$attributes,
            'Identifier' => (string) $id,
        ];
    }

    /**
     * @param  Fluent<array-key, mixed>  $parameters
     * @return array<array-key, mixed>
     */
    protected function edit(string $controller, Fluent $parameters): array
    {
        $id = self::id(Arr::get($this->find($controller, 'edit', $parameters), 'Identifier'));
        $changes = Arr::except($parameters->toArray(), ['Identifier', 'DebtorCode', 'PriceQuoteCode']);

        $this->records[$controller][$id] = [...$this->records[$controller][$id], ...$changes, 'Modified' => $this->now()];
        $this->remember("{$controller}.updated", $id, $changes);

        return $this->present($controller, $this->records[$controller][$id]);
    }

    /**
     * @param  Fluent<array-key, mixed>  $parameters
     * @return array<array-key, mixed>
     */
    protected function createDocument(string $controller, Fluent $parameters): array
    {
        $document = $this->storeDocument($controller, Arr::except($parameters->toArray(), $this->linesKey($controller)), $this->linesFrom($controller, $parameters));
        $this->remember("{$controller}.created", null, $document);

        return $this->present($controller, $document);
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     * @param  array<array-key, mixed>  $lines
     * @return array<array-key, mixed>
     */
    protected function storeDocument(string $controller, array $attributes, array $lines): array
    {
        $id = $this->nextId($controller, $attributes);
        $number = Str::padLeft((string) $id, 4, '0');
        $isQuote = $controller === 'pricequote';

        return $this->records[$controller][$id] = [
            self::CODE_KEYS[$controller] => $isQuote ? "OF{$number}" : "[concept]{$number}",
            'Status' => '0',
            'Date' => Carbon::today()->toDateString(),
            'ReferenceNumber' => '',
            'Description' => '',
            'Created' => $this->now(),
            'Modified' => $this->now(),
            ...Arr::except($attributes, ['Debtor', 'DebtorCode']),
            ...$this->debtorFields($this->debtorFor($attributes, $controller, 'add')),
            'Identifier' => (string) $id,
            $this->linesKey($controller) => collect($lines)->map(fn (mixed $line) => $this->storeLine(Arr::wrap($line)))->values()->all(),
        ];
    }

    /**
     * @param  Fluent<array-key, mixed>  $parameters
     * @return array<array-key, mixed>
     */
    protected function accept(Fluent $parameters): array
    {
        $quote = new Fluent($this->find('pricequote', 'accept', $parameters));
        $id = $quote->integer('Identifier');

        // Like the API: a declined quote stays declined, and the call still succeeds.
        if ($quote->string('Status')->value() === '8') {
            return $this->present('pricequote', $quote->toArray());
        }

        $createInvoice = $parameters->string('CreateInvoice')->value() === 'yes';
        $this->records['pricequote'][$id]['Status'] = $createInvoice ? '4' : '3';
        $this->remember('pricequote.accepted', $id, null);

        if ($createInvoice) {
            $lines = $quote->collect('PriceQuoteLines')->map(fn (mixed $line) => Arr::except(Arr::wrap($line), 'Identifier'))->all();
            $invoice = $this->storeDocument('invoice', ['Debtor' => $quote->get('Debtor')], $lines);
            $this->remember('invoice.created', null, $invoice);
        }

        return $this->present('pricequote', $this->records['pricequote'][$id]);
    }

    /**
     * @param  Fluent<array-key, mixed>  $parameters
     * @return array<array-key, mixed>
     */
    protected function decline(Fluent $parameters): array
    {
        $id = self::id(Arr::get($this->find('pricequote', 'decline', $parameters), 'Identifier'));

        $this->records['pricequote'][$id]['Status'] = '8';
        $this->remember('pricequote.declined', $id, null);

        return $this->present('pricequote', $this->records['pricequote'][$id]);
    }

    /**
     * HostFact archives a quote on `delete`; WeFact has `archive`, for accepted,
     * invoiced and declined quotes only.
     *
     * @param  Fluent<array-key, mixed>  $parameters
     * @return array<array-key, mixed>
     */
    protected function archive(string $action, Fluent $parameters): array
    {
        if ($action !== ($this->driver === Driver::HOSTFACT ? 'delete' : 'archive')) {
            throw $this->fail('pricequote', $action, 'Invalid action');
        }

        $quote = new Fluent($this->find('pricequote', $action, $parameters));
        $id = $quote->integer('Identifier');

        if ($this->driver === Driver::WEFACT && ! $quote->string('Status')->is(['3', '4', '8'])) {
            throw $this->fail('pricequote', $action, 'Only accepted, invoiced and declined quotes can be archived');
        }

        $this->records['pricequote'][$id]['Archived'] = true;
        $this->remember('pricequote.archived', $id, null);

        return [];
    }

    /**
     * @param  Fluent<array-key, mixed>  $parameters
     * @return array<array-key, mixed>
     */
    protected function deleteInvoice(Fluent $parameters): array
    {
        $invoice = new Fluent($this->find('invoice', 'delete', $parameters));
        $id = $invoice->integer('Identifier');

        if ($invoice->string('Status')->value() !== '0') {
            throw $this->fail('invoice', 'delete', 'Only concept invoices can be deleted');
        }

        unset($this->records['invoice'][$id]);
        $this->remember('invoice.deleted', $id, null);

        return [];
    }

    /**
     * @param  Fluent<array-key, mixed>  $parameters
     * @return array<array-key, mixed>
     */
    protected function addLines(string $controller, Fluent $parameters): array
    {
        $id = self::id(Arr::get($this->find($controller, 'add', $parameters), 'Identifier'));
        $key = $this->linesKey($controller);
        $lines = Arr::map($this->linesFrom($controller, $parameters), fn (array $line) => $this->storeLine($line));

        if ($lines === []) {
            throw $this->fail("{$controller}line", 'add', 'At least one line is required');
        }

        $this->records[$controller][$id][$key] = [...Arr::wrap($this->records[$controller][$id][$key] ?? []), ...$lines];
        $this->remember("{$controller}.lines-added", $id, $lines);

        return [$controller => $this->present($controller, $this->records[$controller][$id])];
    }

    /**
     * @param  Fluent<array-key, mixed>  $parameters
     * @return array<array-key, mixed>
     */
    protected function removeLines(string $controller, Fluent $parameters): array
    {
        $id = self::id(Arr::get($this->find($controller, 'delete', $parameters), 'Identifier'));
        $key = $this->linesKey($controller);
        $remove = $parameters->collect($key)->map(fn (mixed $line) => self::id(Arr::get(Arr::wrap($line), 'Identifier')))->values();

        if ($remove->isEmpty()) {
            throw $this->fail("{$controller}line", 'delete', 'At least one identifier is required');
        }

        $remaining = collect(Arr::wrap($this->records[$controller][$id][$key] ?? []))
            ->reject(fn (mixed $line) => $remove->containsStrict(self::id(Arr::get(Arr::wrap($line), 'Identifier'))))
            ->values();

        // Like the API: a quote or invoice keeps at least one line.
        if ($remaining->isEmpty()) {
            throw $this->fail("{$controller}line", 'delete', 'Every line can\'t be removed: at least one line must remain');
        }

        $this->records[$controller][$id][$key] = $remaining->all();
        $this->remember("{$controller}.lines-removed", $id, $remove->all());

        return [$controller => $this->present($controller, $this->records[$controller][$id])];
    }

    /**
     * A stored record as the API answers it, with its totals worked out.
     *
     * @param  array<array-key, mixed>  $row
     * @return array<array-key, mixed>
     */
    protected function present(string $controller, array $row): array
    {
        if (! Str::is(['pricequote', 'invoice'], $controller)) {
            return $row;
        }

        $lines = collect(Arr::wrap($row[$this->linesKey($controller)] ?? []))->map(fn (mixed $line) => new Fluent(Arr::wrap($line)));
        $amountExcl = round($lines->sum(fn (Fluent $line) => self::lineAmount($line)), 2);
        $amountIncl = round($lines->sum(fn (Fluent $line) => self::lineAmount($line) * (1 + $line->float('TaxPercentage') / 100)), 2);

        $totals = ['AmountExcl' => $amountExcl, 'AmountIncl' => $amountIncl];

        if ($controller === 'invoice') {
            $totals[$this->driver === Driver::HOSTFACT ? 'AmountOpen' : 'AmountOutstanding'] = Arr::get($row, 'Status') === '4' ? 0.0 : $amountIncl;
        }

        return [...$row, ...$totals];
    }

    /**
     * A line as the API stores it: with an id, today's date when it has none, and the
     * product's description and price when it names a product without them.
     *
     * @param  array<array-key, mixed>  $line
     * @return array<array-key, mixed>
     */
    protected function storeLine(array $line): array
    {
        $line = new Fluent($line);
        $product = new Fluent(collect($this->records['product'])->firstWhere('ProductCode', $line->get('ProductCode')) ?? []);
        $number = $line->filled('Number') ? $line->float('Number') : 1.0;
        $price = $line->filled('PriceExcl') ? $line->float('PriceExcl') : $product->float('PriceExcl');
        $discount = $line->float('DiscountPercentage');

        return [
            'Date' => Carbon::today()->toDateString(),
            'NumberSuffix' => $product->string('NumberSuffix')->value(),
            'ProductCode' => '',
            'Description' => $product->string('ProductName')->value(),
            'TaxPercentage' => $product->get('TaxPercentage', self::TAX_PERCENTAGE),
            'Periods' => '1',
            'Periodic' => '',
            ...Arr::except($line->toArray(), 'Identifier'),
            'Identifier' => $this->nextLineId++,
            'Number' => (string) $number,
            'PriceExcl' => (string) $price,
            'DiscountPercentage' => $discount,
            'NoDiscountAmountExcl' => round($number * $price, 2),
            // The API sends the discount as a negative amount.
            'DiscountAmountExcl' => -round($number * $price * $discount / 100, 2),
        ];
    }

    /**
     * @param  Fluent<array-key, mixed>  $parameters
     * @return array<int, array<array-key, mixed>>
     */
    protected function linesFrom(string $controller, Fluent $parameters): array
    {
        return $parameters->collect($this->linesKey($controller))->filter(fn (mixed $line) => is_array($line))->values()->all();
    }

    protected function linesKey(string $controller): string
    {
        return $controller === 'pricequote' ? 'PriceQuoteLines' : 'InvoiceLines';
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     * @return array<array-key, mixed>
     */
    protected function debtorFor(array $attributes, string $controller, string $action): array
    {
        $attributes = new Fluent($attributes);

        $debtor = collect($this->records['debtor'])->first(fn (array $debtor, int $id) => ($attributes->filled('Debtor') && $attributes->integer('Debtor') === $id)
            || ($attributes->filled('DebtorCode') && $attributes->string('DebtorCode')->value() === Arr::get($debtor, 'DebtorCode')));

        return is_array($debtor) ? $debtor : throw $this->fail($controller, $action, 'Invalid identifier for debtor');
    }

    /**
     * @param  array<array-key, mixed>  $debtor
     * @return array<array-key, mixed>
     */
    protected function debtorFields(array $debtor): array
    {
        return [
            'Debtor' => Arr::get($debtor, 'Identifier'),
            ...Arr::only($debtor, ['DebtorCode', 'CompanyName', 'Initials', 'SurName']),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    protected function nextId(string $controller, array $attributes): int
    {
        $id = self::id(Arr::get($attributes, 'Identifier'));

        return $id > 0 ? $id : self::id(collect($this->records[$controller])->keys()->max()) + 1;
    }

    protected function now(): string
    {
        return Carbon::now()->toDateTimeString();
    }

    protected function remember(string $change, ?int $subject, mixed $data): void
    {
        $this->changes[] = ['change' => $change, 'subject' => $subject, 'data' => $data];
    }

    protected function fail(string $controller, string $action, string $error): RequestFailed
    {
        return $action === 'show'
            ? NotFound::withErrors($this->driver, $controller, $action, [$error])
            : RequestFailed::withErrors($this->driver, $controller, $action, [$error]);
    }

    protected function assertChanged(string $change, ?int $subject, ?callable $callback, string $message): void
    {
        $matched = collect($this->changes)->contains(fn (array $recorded) => $recorded['change'] === $change
            && ($subject === null || $recorded['subject'] === $subject)
            && ($callback === null || $callback($this->argumentFor($change, $recorded['data'])) === true));

        PHPUnit::assertTrue($matched, $message);
    }

    /**
     * What an assertion callback receives: the created record as its data object, the
     * added lines as `LineItem`s, and the rest as recorded.
     */
    protected function argumentFor(string $change, mixed $data): mixed
    {
        if (! is_array($data)) {
            return $data;
        }

        return match ($change) {
            'debtor.created' => Debtor::fromArray($data, $this),
            'pricequote.created' => Quote::fromArray($this->present('pricequote', $data), $this),
            'invoice.created' => Invoice::fromArray($this->present('invoice', $data), $this),
            'pricequote.lines-added', 'invoice.lines-added' => LineItem::listFrom($data),
            default => $data,
        };
    }

    /**
     * @param  Fluent<array-key, mixed>  $line
     */
    protected static function lineAmount(Fluent $line): float
    {
        return $line->float('NoDiscountAmountExcl') + $line->float('DiscountAmountExcl');
    }

    protected static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    protected static function id(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
