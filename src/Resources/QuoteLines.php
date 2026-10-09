<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Resources;

use Illuminate\Support\Arr;
use SpitsOnline\WeFact\Data\Line;
use SpitsOnline\WeFact\Data\LineItem;
use SpitsOnline\WeFact\WeFact;

/**
 * The lines of one quote: `WeFact::quote($id)->lines()`. The lines themselves are on
 * the quote: `WeFact::quote($id)->get()->lines`.
 */
class QuoteLines
{
    public function __construct(
        protected WeFact $weFact,
        protected int $quote,
    ) {}

    /**
     * Add lines to the end of the quote, in one request. Adding nothing sends nothing.
     */
    public function add(Line ...$lines): void
    {
        if ($lines === []) {
            return;
        }

        $this->weFact->request('pricequoteline', 'add', [
            'Identifier' => $this->quote,
            'PriceQuoteLines' => Arr::map($lines, fn (Line $line) => $line->toArray()),
        ]);
    }

    /**
     * Remove lines from the quote, in one request. Removing nothing sends nothing. The
     * API keeps at least one line on a quote: to replace every line, add the new ones
     * first and remove the old ones after.
     */
    public function remove(LineItem|int ...$lines): void
    {
        if ($lines === []) {
            return;
        }

        $this->weFact->request('pricequoteline', 'delete', [
            'Identifier' => $this->quote,
            'PriceQuoteLines' => Arr::map($lines, fn (LineItem|int $line) => ['Identifier' => $line instanceof LineItem ? $line->id : $line]),
        ]);
    }

    /**
     * Replace every line of the quote with these, in three requests: the current lines
     * are read, the new ones added, and then the old ones removed. Adding first keeps
     * the quote intact when the API refuses a new line, and respects the API's rule that
     * a quote keeps at least one line, which is also why this takes at least one.
     */
    public function replace(Line $line, Line ...$lines): void
    {
        $previous = $this->weFact->quote($this->quote)->get()->lines ?? [];

        $this->add($line, ...$lines);
        $this->remove(...$previous);
    }
}
