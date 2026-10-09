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
}
