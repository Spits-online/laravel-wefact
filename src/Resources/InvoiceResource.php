<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Resources;

use SpitsOnline\WeFact\Data\Invoice;
use SpitsOnline\WeFact\Exceptions\NotFound;
use SpitsOnline\WeFact\WeFact;

/**
 * One invoice: `WeFact::invoice($id)`. Picking an invoice sends no request; every
 * method after it sends exactly one. An `Invoice` you fetched has the same methods.
 */
class InvoiceResource
{
    public function __construct(
        protected WeFact $weFact,
        protected int $id,
    ) {}

    /**
     * The invoice, with its lines.
     *
     * @throws NotFound when it doesn't exist
     */
    public function get(): Invoice
    {
        return Invoice::fromArray($this->weFact->record('invoice', 'show', ['Identifier' => $this->id]), $this->weFact);
    }

    /**
     * The invoice's lines, to add or remove them.
     */
    public function lines(): InvoiceLines
    {
        return new InvoiceLines($this->weFact, $this->id);
    }

    /**
     * Delete the invoice. Only concept invoices can be deleted; credit a sent one instead.
     */
    public function delete(): void
    {
        $this->weFact->request('invoice', 'delete', ['Identifier' => $this->id]);
    }
}
