<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Resources;

use DateTimeInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\LazyCollection;
use SpitsOnline\WeFact\Concerns\BuildsFilters;
use SpitsOnline\WeFact\Concerns\Paginates;
use SpitsOnline\WeFact\Data\Debtor;
use SpitsOnline\WeFact\Data\Invoice;
use SpitsOnline\WeFact\Data\Line;
use SpitsOnline\WeFact\Enums\InvoiceStatus;
use SpitsOnline\WeFact\Exceptions\NotFound;
use SpitsOnline\WeFact\WeFact;

/**
 * Every invoice: `WeFact::invoices()`. To work with one invoice, use `WeFact::invoice($id)`,
 * or the `Invoice` that `find()` returns.
 */
class Invoices
{
    use BuildsFilters;
    use Paginates;

    public function __construct(
        protected WeFact $weFact,
    ) {}

    /**
     * Every invoice, newest first, fetched a page at a time as you iterate. The
     * invoices have no `$lines`; `find()` returns those.
     *
     * A debtor's concept invoices: `get(status: InvoiceStatus::CONCEPT, debtor: $id)`.
     *
     * @param  InvoiceStatus|list<InvoiceStatus>|null  $status
     * @param  ?DateTimeInterface  $modifiedSince  only invoices changed at or after this moment
     * @return LazyCollection<int, Invoice>
     */
    public function get(InvoiceStatus|array|null $status = null, Debtor|int|null $debtor = null, ?DateTimeInterface $modifiedSince = null): LazyCollection
    {
        return $this->paginate('invoice', 'invoices', ['status' => self::status($status)] + self::ofDebtor($debtor) + self::modifiedSince($modifiedSince))
            ->map(fn (array $invoice) => Invoice::fromArray($invoice, $this->weFact));
    }

    /**
     * The invoice with this id, with its lines, or null when it doesn't exist.
     */
    public function find(int $id): ?Invoice
    {
        return $this->show(['Identifier' => $id]);
    }

    /**
     * The invoice with this invoice code (`F2026-0001`, or `[concept]0009` for a
     * concept), with its lines, or null when it doesn't exist.
     */
    public function findByCode(string $code): ?Invoice
    {
        return $this->show(['InvoiceCode' => $code]);
    }

    /**
     * Create a concept invoice for the debtor and return it as the API saved it.
     *
     * @param  list<Line>  $lines
     * @param  ?DateTimeInterface  $date  the invoice date; today when left out
     * @param  array<string, mixed>  $attributes  any other invoice field the API documents, in its own keys
     */
    public function create(
        Debtor|int $debtor,
        array $lines,
        ?string $referenceNumber = null,
        ?DateTimeInterface $date = null,
        ?string $description = null,
        array $attributes = [],
    ): Invoice {
        $invoice = $this->weFact->record('invoice', 'add', Arr::whereNotNull([
            'Debtor' => $debtor instanceof Debtor ? $debtor->id : $debtor,
            'ReferenceNumber' => $referenceNumber,
            'Date' => self::day($date),
            'Description' => $description,
            'InvoiceLines' => Arr::map($lines, fn (Line $line) => $line->toArray()),
        ]) + $attributes);

        return Invoice::fromArray($invoice, $this->weFact);
    }

    /**
     * @param  array<string, int|string>  $key
     */
    protected function show(array $key): ?Invoice
    {
        try {
            return Invoice::fromArray($this->weFact->record('invoice', 'show', $key), $this->weFact);
        } catch (NotFound) {
            return null;
        }
    }
}
