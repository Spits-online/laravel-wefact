<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Data;

use Carbon\CarbonImmutable;
use Illuminate\Support\Fluent;
use SpitsOnline\WeFact\Concerns\ReadsPayload;
use SpitsOnline\WeFact\Enums\DomainStatus;

/**
 * A domain HostFact manages for a debtor. `$raw` holds the full payload.
 */
final readonly class Domain
{
    use ReadsPayload;

    /**
     * @param  string  $name  the full name, e.g. `example.com`
     * @param  string  $sld  the name without its extension, e.g. `example`
     * @param  string  $tld  the extension, e.g. `com`
     * @param  ?bool  $autoRenew  null on `WeFact::domains()->get()`, which doesn't send it
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $sld,
        public string $tld,
        public int $debtorId,
        public string $debtorCode,
        public ?DomainStatus $status,
        public ?string $registrar,
        public ?bool $autoRenew,
        public ?CarbonImmutable $registrationDate,
        public ?CarbonImmutable $expirationDate,
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
        $sld = $data->string('Domain')->value();
        $tld = $data->string('Tld')->value();

        return new self(
            id: $data->integer('Identifier'),
            name: $tld === '' ? $sld : "{$sld}.{$tld}",
            sld: $sld,
            tld: $tld,
            debtorId: $data->integer('Debtor'),
            debtorCode: $data->string('DebtorCode')->value(),
            status: self::enum($data, 'Status', DomainStatus::class),
            registrar: self::text($data, 'RegistrarName') ?? self::text($data, 'RegistrarInfo.Name'),
            autoRenew: $data->has('DomainAutoRenew') ? $data->string('DomainAutoRenew')->value() === 'on' : null,
            registrationDate: self::date($data, 'RegistrationDate'),
            expirationDate: self::date($data, 'ExpirationDate'),
            modifiedAt: self::date($data, 'Modified'),
            raw: $payload,
        );
    }
}
