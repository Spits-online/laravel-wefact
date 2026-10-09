<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Data;

use Carbon\CarbonImmutable;
use Illuminate\Support\Fluent;
use SpitsOnline\WeFact\Concerns\ReadsPayload;
use SpitsOnline\WeFact\Enums\Period;
use SpitsOnline\WeFact\Enums\ProductType;

/**
 * A product from the catalogue. Pass its `code` to `Line::create(productCode: …)`.
 * `$raw` holds the full payload.
 */
final readonly class Product
{
    use ReadsPayload;

    /**
     * @param  ?Period  $period  how often the price is billed; null for a one-off price
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public ?string $keyPhrase,
        public ?string $description,
        public ?ProductType $type,
        public float $priceExcl,
        public ?Period $period,
        public ?string $unit,
        public ?float $taxPercentage,
        public ?CarbonImmutable $modifiedAt,
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

        return new self(
            id: $data->integer('Identifier'),
            code: $data->string('ProductCode')->value(),
            name: $data->string('ProductName')->value(),
            keyPhrase: self::text($data, 'ProductKeyPhrase'),
            description: self::text($data, 'ProductDescription'),
            type: self::enum($data, 'ProductType', ProductType::class),
            priceExcl: self::number($data, 'PriceExcl'),
            period: self::enum($data, 'PricePeriod', Period::class),
            unit: self::text($data, 'NumberSuffix'),
            taxPercentage: self::optionalNumber($data, 'TaxPercentage'),
            modifiedAt: self::date($data, 'Modified'),
            raw: $payload,
        );
    }
}
