<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Resources;

use Illuminate\Support\Arr;
use SpitsOnline\WeFact\Data\Debtor;
use SpitsOnline\WeFact\Data\Invoice;
use SpitsOnline\WeFact\Data\Line;
use SpitsOnline\WeFact\Enums\InvoiceStatus;
use SpitsOnline\WeFact\Exceptions\NotFound;
use SpitsOnline\WeFact\WeFact;

/**
 * One debtor: `WeFact::debtor($id)`. Picking a debtor sends no request; `get()` and
 * `update()` send one, `bill()` two. A `Debtor` you fetched has the same methods.
 */
class DebtorResource
{
    public function __construct(
        protected WeFact $weFact,
        protected int $id,
    ) {}

    /**
     * The debtor, with every field.
     *
     * @throws NotFound when it doesn't exist
     */
    public function get(): Debtor
    {
        return Debtor::fromArray($this->weFact->record('debtor', 'show', ['Identifier' => $this->id]), $this->weFact);
    }

    /**
     * Change the debtor and return it as the API saved it. Only the arguments you pass
     * are changed; pass `''` to clear a field.
     *
     * @param  array<string, mixed>  $attributes  any other debtor field the API documents, in its own keys
     */
    public function update(
        ?string $companyName = null,
        ?string $initials = null,
        ?string $surName = null,
        ?string $emailAddress = null,
        ?string $phoneNumber = null,
        ?string $mobileNumber = null,
        ?string $address = null,
        ?string $zipCode = null,
        ?string $city = null,
        ?string $country = null,
        ?string $companyNumber = null,
        ?string $taxNumber = null,
        ?string $comment = null,
        array $attributes = [],
    ): Debtor {
        $fields = Debtors::fields($companyName, $initials, $surName, $emailAddress, $phoneNumber, $mobileNumber, $address, $zipCode, $city, $country, $companyNumber, $taxNumber, $comment);

        return Debtor::fromArray($this->weFact->record('debtor', 'edit', ['Identifier' => $this->id] + $fields + $attributes), $this->weFact);
    }

    /**
     * Put lines on the debtor's concept invoice, to be sent later: the newest concept
     * invoice when the debtor has one, or a new one. Returns that invoice, with its
     * lines. Two requests: one to find the concept invoice, one to add the lines.
     *
     * A concept invoice can also come from accepting a quote with `createInvoice`, so
     * lines may land on that one; they are all the debtor's open work to be invoiced.
     */
    public function bill(Line $line, Line ...$lines): Invoice
    {
        $lines = [$line, ...array_values($lines)];
        $concept = $this->weFact->invoices()->get(status: InvoiceStatus::CONCEPT, debtor: $this->id)->first();

        if ($concept === null) {
            return $this->weFact->invoices()->create(debtor: $this->id, lines: $lines);
        }

        return Invoice::fromArray($this->weFact->record('invoiceline', 'add', [
            'Identifier' => $concept->id,
            'InvoiceLines' => Arr::map($lines, fn (Line $line) => $line->toArray()),
        ], 'invoice'), $this->weFact);
    }
}
