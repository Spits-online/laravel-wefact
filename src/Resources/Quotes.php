<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Resources;

use DateTimeInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\LazyCollection;
use SpitsOnline\WeFact\Concerns\BuildsFilters;
use SpitsOnline\WeFact\Concerns\Paginates;
use SpitsOnline\WeFact\Data\Debtor;
use SpitsOnline\WeFact\Data\Line;
use SpitsOnline\WeFact\Data\Quote;
use SpitsOnline\WeFact\Enums\QuoteStatus;
use SpitsOnline\WeFact\Exceptions\NotFound;
use SpitsOnline\WeFact\WeFact;

/**
 * Every quote: `WeFact::quotes()`. To work with one quote, use `WeFact::quote($id)`,
 * or the `Quote` that `find()` returns.
 */
class Quotes
{
    use BuildsFilters;
    use Paginates;

    public function __construct(
        protected WeFact $weFact,
    ) {}

    /**
     * Every quote, newest first, fetched a page at a time as you iterate. Archived
     * quotes are left out. The quotes have no `$lines`; `find()` returns those.
     *
     * @param  QuoteStatus|list<QuoteStatus>|null  $status
     * @param  ?DateTimeInterface  $modifiedSince  only quotes changed at or after this moment
     * @return LazyCollection<int, Quote>
     */
    public function get(QuoteStatus|array|null $status = null, Debtor|int|null $debtor = null, ?DateTimeInterface $modifiedSince = null): LazyCollection
    {
        return $this->paginate('pricequote', 'pricequotes', ['status' => self::status($status)] + self::ofDebtor($debtor) + self::modifiedSince($modifiedSince))
            ->map(fn (array $quote) => Quote::fromArray($quote, $this->weFact));
    }

    /**
     * The quote with this id, with its lines, or null when it doesn't exist.
     */
    public function find(int $id): ?Quote
    {
        return $this->show(['Identifier' => $id]);
    }

    /**
     * The quote with this quote code (`OF0014`), with its lines, or null when it doesn't exist.
     */
    public function findByCode(string $code): ?Quote
    {
        return $this->show(['PriceQuoteCode' => $code]);
    }

    /**
     * Create a concept quote for the debtor and return it as the API saved it.
     *
     * @param  list<Line>  $lines
     * @param  ?DateTimeInterface  $date  the quote date; today when left out
     * @param  array<string, mixed>  $attributes  any other quote field the API documents, in its own keys
     */
    public function create(
        Debtor|int $debtor,
        array $lines,
        ?string $referenceNumber = null,
        ?DateTimeInterface $date = null,
        ?string $description = null,
        array $attributes = [],
    ): Quote {
        $quote = $this->weFact->record('pricequote', 'add', Arr::whereNotNull([
            'Debtor' => $debtor instanceof Debtor ? $debtor->id : $debtor,
            'ReferenceNumber' => $referenceNumber,
            'Date' => self::day($date),
            'Description' => $description,
            'PriceQuoteLines' => Arr::map($lines, fn (Line $line) => $line->toArray()),
        ]) + $attributes);

        return Quote::fromArray($quote, $this->weFact);
    }

    /**
     * @param  array<string, int|string>  $key
     */
    protected function show(array $key): ?Quote
    {
        try {
            return Quote::fromArray($this->weFact->record('pricequote', 'show', $key), $this->weFact);
        } catch (NotFound) {
            return null;
        }
    }
}
