<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Data;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Fluent;
use SpitsOnline\WeFact\Concerns\ReadsPayload;
use SpitsOnline\WeFact\Concerns\SerializesWithoutClient;
use SpitsOnline\WeFact\Enums\QuoteStatus;
use SpitsOnline\WeFact\Resources\QuoteLines;
use SpitsOnline\WeFact\WeFact;

/**
 * A quote ("offerte"). It knows its id, so it can act on itself, one request each:
 * `$quote->accept()`, `$quote->lines()->add($line)` and the others, the same as
 * `WeFact::quote($id)`.
 *
 * `WeFact::quotes()->get()` doesn't return lines: `$lines` is null there, and a list
 * when the quote came from `find()` or `get()`. `$raw` holds the full payload.
 */
final readonly class Quote
{
    use ReadsPayload;
    use SerializesWithoutClient;

    /**
     * @param  ?QuoteStatus  $status  null when the API sends a status this package doesn't know
     * @param  ?array<int, LineItem>  $lines
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(
        public int $id,
        public string $code,
        public int $debtorId,
        public string $debtorCode,
        public ?string $companyName,
        public ?string $initials,
        public ?string $surName,
        public ?string $referenceNumber,
        public ?QuoteStatus $status,
        public ?CarbonImmutable $date,
        public ?CarbonImmutable $expirationDate,
        public float $amountExcl,
        public float $amountIncl,
        public ?array $lines,
        public ?CarbonImmutable $createdAt,
        public ?CarbonImmutable $modifiedAt,
        public array $raw,
        private WeFact $weFact,
    ) {}

    /**
     * @param  array<array-key, mixed>  $payload
     *
     * @internal
     */
    public static function fromArray(array $payload, WeFact $weFact): self
    {
        $data = new Fluent($payload);

        return new self(
            id: $data->integer('Identifier'),
            code: $data->string('PriceQuoteCode')->value(),
            debtorId: $data->integer('Debtor'),
            debtorCode: $data->string('DebtorCode')->value(),
            companyName: self::text($data, 'CompanyName'),
            initials: self::text($data, 'Initials'),
            surName: self::text($data, 'SurName'),
            referenceNumber: self::text($data, 'ReferenceNumber'),
            status: self::enum($data, 'Status', QuoteStatus::class),
            date: self::date($data, 'Date'),
            expirationDate: self::date($data, 'ExpirationDate'),
            amountExcl: self::number($data, 'AmountExcl'),
            amountIncl: self::number($data, 'AmountIncl'),
            lines: $data->has('PriceQuoteLines') ? LineItem::listFrom($data->array('PriceQuoteLines')) : null,
            createdAt: self::date($data, 'Created'),
            modifiedAt: self::date($data, 'Modified'),
            raw: $payload,
            weFact: $weFact,
        );
    }

    /**
     * The quote's lines, to add or remove them. `$quote->lines` holds the lines themselves.
     */
    public function lines(): QuoteLines
    {
        return $this->weFact->quote($this->id)->lines();
    }

    /**
     * See `QuoteResource::update()`.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(
        ?string $referenceNumber = null,
        ?DateTimeInterface $date = null,
        ?QuoteStatus $status = null,
        ?string $description = null,
        array $attributes = [],
    ): self {
        return $this->weFact->quote($this->id)->update($referenceNumber, $date, $status, $description, $attributes);
    }

    /**
     * See `QuoteResource::accept()`.
     */
    public function accept(bool $createInvoice = false): self
    {
        return $this->weFact->quote($this->id)->accept($createInvoice);
    }

    public function decline(): self
    {
        return $this->weFact->quote($this->id)->decline();
    }

    public function archive(): void
    {
        $this->weFact->quote($this->id)->archive();
    }
}
