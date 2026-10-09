<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Data;

use Carbon\CarbonImmutable;
use Illuminate\Support\Fluent;
use SpitsOnline\WeFact\Concerns\ReadsPayload;
use SpitsOnline\WeFact\Enums\Period;
use SpitsOnline\WeFact\Enums\ProductType;
use SpitsOnline\WeFact\Enums\SubscriptionStatus;

/**
 * A subscription: a line the API bills a debtor for periodically. This covers every
 * subscription, including the ones attached to a domain or hosting account (`$type`).
 * `$raw` holds the full payload.
 */
final readonly class Subscription
{
    use ReadsPayload;

    /**
     * @param  ?Period  $period  how often the line is billed; with `$periods`, e.g. every 3 months
     * @param  ?CarbonImmutable  $startDate  the start of the period that is billed next
     * @param  ?CarbonImmutable  $nextDate  when the API creates the next invoice for it
     * @param  ?ProductType  $type  what the subscription is for: a domain, hosting, or `OTHER`
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(
        public int $id,
        public int $debtorId,
        public string $debtorCode,
        public ?string $productCode,
        public ?string $description,
        public float $quantity,
        public ?string $unit,
        public float $priceExcl,
        public float $discountPercentage,
        public ?float $taxPercentage,
        public ?Period $period,
        public int $periods,
        public ?CarbonImmutable $startDate,
        public ?CarbonImmutable $endDate,
        public ?CarbonImmutable $nextDate,
        public ?CarbonImmutable $terminationDate,
        public ?SubscriptionStatus $status,
        public ?ProductType $type,
        public float $amountExcl,
        public float $amountIncl,
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
            debtorId: $data->integer('Debtor'),
            debtorCode: $data->string('DebtorCode')->value(),
            productCode: self::text($data, 'ProductCode'),
            description: self::text($data, 'Description'),
            quantity: self::number($data, 'Number', 1),
            unit: self::text($data, 'NumberSuffix'),
            priceExcl: self::number($data, 'PriceExcl'),
            discountPercentage: self::number($data, 'DiscountPercentage'),
            taxPercentage: self::optionalNumber($data, 'TaxPercentage'),
            period: self::enum($data, 'Periodic', Period::class),
            periods: $data->integer('Periods') ?: 1,
            // HostFact names the period StartPeriod/EndPeriod, WeFact StartDate/EndDate.
            startDate: self::date($data, 'StartDate') ?? self::date($data, 'StartPeriod'),
            endDate: self::date($data, 'EndDate') ?? self::date($data, 'EndPeriod'),
            nextDate: self::date($data, 'NextDate'),
            terminationDate: self::date($data, 'TerminationDate'),
            status: self::enum($data, 'Status', SubscriptionStatus::class),
            type: self::enum($data, 'PeriodicType', ProductType::class),
            amountExcl: self::number($data, 'AmountExcl'),
            amountIncl: self::number($data, 'AmountIncl'),
            modifiedAt: self::date($data, 'Modified'),
            raw: $payload,
        );
    }
}
