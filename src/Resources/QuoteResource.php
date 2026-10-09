<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Resources;

use DateTimeInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use SpitsOnline\WeFact\Concerns\WritesPayload;
use SpitsOnline\WeFact\Data\Quote;
use SpitsOnline\WeFact\Enums\Driver;
use SpitsOnline\WeFact\Enums\QuoteStatus;
use SpitsOnline\WeFact\Exceptions\NotFound;
use SpitsOnline\WeFact\Exceptions\RequestFailed;
use SpitsOnline\WeFact\WeFact;

/**
 * One quote: `WeFact::quote($id)`. Picking a quote sends no request; every method
 * after it sends exactly one. A `Quote` you fetched has the same methods.
 */
class QuoteResource
{
    use WritesPayload;

    public function __construct(
        protected WeFact $weFact,
        protected int $id,
    ) {}

    /**
     * The quote, with its lines.
     *
     * @throws NotFound when it doesn't exist
     */
    public function get(): Quote
    {
        return $this->quote('show');
    }

    /**
     * The quote's lines, to add or remove them.
     */
    public function lines(): QuoteLines
    {
        return new QuoteLines($this->weFact, $this->id);
    }

    /**
     * Change the quote and return it as the API saved it. Only the arguments you pass
     * are changed.
     *
     * @param  array<string, mixed>  $attributes  any other quote field the API documents, in its own keys
     */
    public function update(
        ?string $referenceNumber = null,
        ?DateTimeInterface $date = null,
        ?QuoteStatus $status = null,
        ?string $description = null,
        array $attributes = [],
    ): Quote {
        return $this->quote('edit', Arr::whereNotNull([
            'ReferenceNumber' => $referenceNumber,
            'Date' => self::day($date),
            'Status' => $status?->value,
            'Description' => $description,
        ]) + $attributes);
    }

    /**
     * Mark the quote as accepted. With `$createInvoice`, the API also turns it into a
     * concept invoice for the debtor, and the quote's status becomes `INVOICED`.
     *
     * @throws RequestFailed when the quote can't be accepted, e.g. because it was declined
     */
    public function accept(bool $createInvoice = false): Quote
    {
        $quote = $this->quote('accept', $createInvoice ? ['CreateInvoice' => 'yes'] : []);

        // The API answers success for a declined quote, but leaves it declined.
        return $quote->status?->isAccepted() === true
            ? $quote
            : throw $this->refused('accept', "Quote {$quote->code} is {$this->statusName($quote)} and can't be accepted.");
    }

    /**
     * Mark the quote as declined. An accepted quote can be declined too.
     */
    public function decline(): Quote
    {
        $quote = $this->quote('decline');

        return $quote->status === QuoteStatus::DECLINED
            ? $quote
            : throw $this->refused('decline', "Quote {$quote->code} is {$this->statusName($quote)} and can't be declined.");
    }

    /**
     * Archive the quote, so it no longer appears in `WeFact::quotes()->get()`. Neither
     * API deletes quotes through this package: HostFact archives on `delete`, and
     * WeFact's `archive` only takes accepted, invoiced and declined quotes.
     */
    public function archive(): void
    {
        $this->weFact->request('pricequote', $this->weFact->driver() === Driver::HOSTFACT ? 'delete' : 'archive', ['Identifier' => $this->id]);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function quote(string $action, array $parameters = []): Quote
    {
        return Quote::fromArray($this->weFact->record('pricequote', $action, ['Identifier' => $this->id] + $parameters), $this->weFact);
    }

    protected function refused(string $action, string $reason): RequestFailed
    {
        return RequestFailed::withErrors($this->weFact->driver(), 'pricequote', $action, [$reason]);
    }

    protected function statusName(Quote $quote): string
    {
        return $quote->status === null ? 'in an unknown status' : Str::lower($quote->status->name);
    }
}
