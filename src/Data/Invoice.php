<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Data;

use Carbon\CarbonImmutable;
use Illuminate\Support\Fluent;
use SpitsOnline\WeFact\Concerns\ReadsPayload;
use SpitsOnline\WeFact\Concerns\SerializesWithoutClient;
use SpitsOnline\WeFact\Enums\InvoiceStatus;
use SpitsOnline\WeFact\Resources\InvoiceLines;
use SpitsOnline\WeFact\WeFact;

/**
 * An invoice. It knows its id, so it can act on itself: `$invoice->lines()->add($line)`
 * and `$invoice->delete()`, the same as `WeFact::invoice($id)`.
 *
 * A draft invoice has a code like `[concept]0009` until it is sent. `WeFact::invoices()->get()`
 * doesn't return lines: `$lines` is null there, and a list when the invoice came from
 * `find()` or `get()`. `$raw` holds the full payload.
 */
final readonly class Invoice
{
    use ReadsPayload;
    use SerializesWithoutClient;

    /**
     * @param  ?InvoiceStatus  $status  null when the API sends a status this package doesn't know
     * @param  float  $amountOpen  what is still to be paid, including VAT
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
        public ?InvoiceStatus $status,
        public ?CarbonImmutable $date,
        public ?CarbonImmutable $payBefore,
        public float $amountExcl,
        public float $amountIncl,
        public float $amountOpen,
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
        $amountIncl = self::number($data, 'AmountIncl');

        return new self(
            id: $data->integer('Identifier'),
            code: $data->string('InvoiceCode')->value(),
            debtorId: $data->integer('Debtor'),
            debtorCode: $data->string('DebtorCode')->value(),
            companyName: self::text($data, 'CompanyName'),
            initials: self::text($data, 'Initials'),
            surName: self::text($data, 'SurName'),
            referenceNumber: self::text($data, 'ReferenceNumber'),
            status: self::enum($data, 'Status', InvoiceStatus::class),
            date: self::date($data, 'Date'),
            payBefore: self::date($data, 'PayBefore'),
            amountExcl: self::number($data, 'AmountExcl'),
            amountIncl: $amountIncl,
            // HostFact's list sends AmountOpen and WeFact's AmountOutstanding; a full
            // HostFact invoice sends only what is paid.
            amountOpen: self::optionalNumber($data, 'AmountOutstanding') ?? self::optionalNumber($data, 'AmountOpen') ?? round($amountIncl - self::number($data, 'AmountPaid'), 2),
            lines: $data->has('InvoiceLines') ? LineItem::listFrom($data->array('InvoiceLines')) : null,
            createdAt: self::date($data, 'Created'),
            modifiedAt: self::date($data, 'Modified'),
            raw: $payload,
            weFact: $weFact,
        );
    }

    /**
     * The invoice's lines, to add or remove them. `$invoice->lines` holds the lines themselves.
     */
    public function lines(): InvoiceLines
    {
        return $this->weFact->invoice($this->id)->lines();
    }

    /**
     * See `InvoiceResource::delete()`.
     */
    public function delete(): void
    {
        $this->weFact->invoice($this->id)->delete();
    }
}
