<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Resources;

use Illuminate\Support\Arr;
use SpitsOnline\WeFact\Data\Line;
use SpitsOnline\WeFact\Data\LineItem;
use SpitsOnline\WeFact\WeFact;

/**
 * The lines of one invoice: `WeFact::invoice($id)->lines()`. The lines themselves are
 * on the invoice: `WeFact::invoice($id)->get()->lines`.
 */
class InvoiceLines
{
    public function __construct(
        protected WeFact $weFact,
        protected int $invoice,
    ) {}

    /**
     * Add lines to the end of the invoice, in one request. Adding nothing sends nothing.
     */
    public function add(Line ...$lines): void
    {
        if ($lines === []) {
            return;
        }

        $this->weFact->request('invoiceline', 'add', [
            'Identifier' => $this->invoice,
            'InvoiceLines' => Arr::map($lines, fn (Line $line) => $line->toArray()),
        ]);
    }

    /**
     * Remove lines from the invoice, in one request. Removing nothing sends nothing. The
     * API keeps at least one line on a invoice: to replace every line, add the new ones
     * first and remove the old ones after.
     */
    public function remove(LineItem|int ...$lines): void
    {
        if ($lines === []) {
            return;
        }

        $this->weFact->request('invoiceline', 'delete', [
            'Identifier' => $this->invoice,
            'InvoiceLines' => Arr::map($lines, fn (LineItem|int $line) => ['Identifier' => $line instanceof LineItem ? $line->id : $line]),
        ]);
    }

    /**
     * Replace every line of the invoice with these, in three requests: the current lines
     * are read, the new ones added, and then the old ones removed. Adding first keeps
     * the invoice intact when the API refuses a new line, and respects the API's rule that
     * a invoice keeps at least one line, which is also why this takes at least one.
     */
    public function replace(Line $line, Line ...$lines): void
    {
        $previous = $this->weFact->invoice($this->invoice)->get()->lines ?? [];

        $this->add($line, ...$lines);
        $this->remove(...$previous);
    }
}
