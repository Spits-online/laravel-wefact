<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Data;

use Carbon\CarbonImmutable;
use Illuminate\Support\Fluent;
use SpitsOnline\WeFact\Concerns\ReadsPayload;
use SpitsOnline\WeFact\Enums\Period;

/**
 * A line of a quote or an invoice, as the API stores it. Remove it with
 * `$quote->lines()->remove($item)`; to add one, build a `Line`.
 */
final readonly class LineItem
{
    use ReadsPayload;

    /**
     * @param  float  $amountExcl  the line total excluding VAT, after its discount
     * @param  float  $discountAmountExcl  what the discount takes off the line total
     * @param  ?Period  $period  how often the line is billed; null for a one-off line
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(
        public int $id,
        public ?string $description,
        public float $quantity,
        public ?string $unit,
        public float $priceExcl,
        public float $discountPercentage,
        public ?float $taxPercentage,
        public ?string $productCode,
        public ?CarbonImmutable $date,
        public ?Period $period,
        public int $periods,
        public ?CarbonImmutable $startDate,
        public ?CarbonImmutable $endDate,
        public float $amountExcl,
        public float $discountAmountExcl,
        public array $raw,
    ) {}

    /**
     * @param  array<array-key, mixed>  $payload
     *
     * @internal
     */
    public static function fromArray(array $payload): self
    {
        $data = new Fluent($payload);
        // The API sends the discount as a negative amount, e.g. `-9.66`.
        $discount = self::number($data, 'DiscountAmountExcl');

        return new self(
            id: $data->integer('Identifier'),
            description: self::text($data, 'Description'),
            quantity: self::number($data, 'Number', 1),
            unit: self::text($data, 'NumberSuffix'),
            priceExcl: self::number($data, 'PriceExcl'),
            discountPercentage: self::number($data, 'DiscountPercentage'),
            taxPercentage: self::optionalNumber($data, 'TaxPercentage'),
            productCode: self::text($data, 'ProductCode'),
            date: self::date($data, 'Date'),
            period: self::enum($data, 'Periodic', Period::class),
            periods: $data->integer('Periods') ?: 1,
            // HostFact names the period StartPeriod/EndPeriod, WeFact StartDate/EndDate.
            startDate: self::date($data, 'StartDate') ?? self::date($data, 'StartPeriod'),
            endDate: self::date($data, 'EndDate') ?? self::date($data, 'EndPeriod'),
            amountExcl: round(self::number($data, 'NoDiscountAmountExcl') + $discount, 2),
            discountAmountExcl: abs($discount),
            raw: $payload,
        );
    }

    /**
     * @param  array<array-key, mixed>  $lines
     * @return array<int, self>
     *
     * @internal
     */
    public static function listFrom(array $lines): array
    {
        return collect($lines)->filter(fn (mixed $row) => is_array($row))->map(fn (array $line) => self::fromArray($line))->values()->all();
    }
}
