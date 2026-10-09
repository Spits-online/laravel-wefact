<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Data;

use DateTimeInterface;
use Illuminate\Support\Arr;
use SpitsOnline\WeFact\Concerns\WritesPayload;
use SpitsOnline\WeFact\Exceptions\InvalidLine;

/**
 * A line you add to a quote or an invoice. Build it with `Line::create()`. The lines you
 * read back are `LineItem`s, which carry their id.
 *
 * Leave out the price to use the product's price from the API, and the date to use
 * today. A line without a product code or price is a text line.
 */
final readonly class Line
{
    use WritesPayload;

    /**
     * @param  array<string, mixed>  $attributes  any other line field the API documents, in its own keys
     */
    public function __construct(
        public ?string $description = null,
        public ?float $priceExcl = null,
        public float $quantity = 1,
        public ?string $productCode = null,
        public ?string $unit = null,
        public ?DateTimeInterface $date = null,
        public ?float $discountPercentage = null,
        public ?float $taxPercentage = null,
        public array $attributes = [],
    ) {
        if (blank($description) && blank($productCode)) {
            throw InvalidLine::empty();
        }
    }

    /**
     * @param  ?float  $priceExcl  the price per unit, excluding VAT
     * @param  ?string  $unit  e.g. `hour`, shown after the quantity
     * @param  ?float  $discountPercentage  0 to 100
     * @param  ?float  $taxPercentage  0 to 100; the API's default rate when left out
     * @param  array<string, mixed>  $attributes  any other line field the API documents, in its own keys
     */
    public static function create(
        ?string $description = null,
        ?float $priceExcl = null,
        float $quantity = 1,
        ?string $productCode = null,
        ?string $unit = null,
        ?DateTimeInterface $date = null,
        ?float $discountPercentage = null,
        ?float $taxPercentage = null,
        array $attributes = [],
    ): self {
        return new self($description, $priceExcl, $quantity, $productCode, $unit, $date, $discountPercentage, $taxPercentage, $attributes);
    }

    /**
     * The line in the API's keys.
     *
     * @return array<string, mixed>
     *
     * @internal
     */
    public function toArray(): array
    {
        return Arr::whereNotNull([
            'Date' => self::day($this->date),
            'Number' => self::decimal($this->quantity),
            'NumberSuffix' => $this->unit,
            'ProductCode' => $this->productCode,
            'Description' => $this->description,
            'PriceExcl' => self::decimal($this->priceExcl),
            'DiscountPercentage' => self::decimal($this->discountPercentage),
            'TaxPercentage' => self::decimal($this->taxPercentage),
        ]) + $this->attributes;
    }
}
