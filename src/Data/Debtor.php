<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Data;

use Carbon\CarbonImmutable;
use Illuminate\Support\Fluent;
use SpitsOnline\WeFact\Concerns\ReadsPayload;
use SpitsOnline\WeFact\Concerns\SerializesWithoutClient;
use SpitsOnline\WeFact\WeFact;

/**
 * A debtor ("debiteur"): a customer. It knows its id, so it can update
 * itself: `$debtor->update(comment: '…')`.
 *
 * `WeFact::debtors()->get()` returns the API's short version of each debtor, with
 * the code, names, email address and modification date; the other fields are null
 * there. `find()` and `get()` return every field. `$raw` holds the full payload.
 */
final readonly class Debtor
{
    use ReadsPayload;
    use SerializesWithoutClient;

    /**
     * @param  ?string  $invoiceAddress  the street of the billing address, when it differs from `$address`
     * @param  ?string  $initials  the API's "Initials" field, which usually holds the first name
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(
        public int $id,
        public string $code,
        public ?string $companyName,
        public ?string $initials,
        public ?string $surName,
        public ?string $emailAddress,
        public ?string $phoneNumber,
        public ?string $mobileNumber,
        public ?string $address,
        public ?string $zipCode,
        public ?string $city,
        public ?string $country,
        public ?string $invoiceAddress,
        public ?string $companyNumber,
        public ?string $taxNumber,
        public ?string $comment,
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
            code: $data->string('DebtorCode')->value(),
            companyName: self::text($data, 'CompanyName'),
            initials: self::text($data, 'Initials'),
            surName: self::text($data, 'SurName'),
            emailAddress: self::text($data, 'EmailAddress'),
            phoneNumber: self::text($data, 'PhoneNumber'),
            mobileNumber: self::text($data, 'MobileNumber'),
            address: self::text($data, 'Address'),
            zipCode: self::text($data, 'ZipCode'),
            city: self::text($data, 'City'),
            country: self::text($data, 'Country'),
            invoiceAddress: self::text($data, 'InvoiceAddress'),
            companyNumber: self::text($data, 'CompanyNumber'),
            taxNumber: self::text($data, 'TaxNumber'),
            comment: self::text($data, 'Comment'),
            createdAt: self::date($data, 'Created'),
            modifiedAt: self::date($data, 'Modified'),
            raw: $payload,
            weFact: $weFact,
        );
    }

    /**
     * Change the debtor and return it as the API saved it. Only the arguments you
     * pass are changed; pass `''` to clear a field. See `DebtorResource::update()`.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(
        ?string $companyName = null,
        ?string $initials = null,
        ?string $surName = null,
        ?string $emailAddress = null,
        ?string $phoneNumber = null,
        ?string $mobileNumber = null,
        ?string $address = null,
        ?string $zipCode = null,
        ?string $city = null,
        ?string $country = null,
        ?string $companyNumber = null,
        ?string $taxNumber = null,
        ?string $comment = null,
        array $attributes = [],
    ): self {
        return $this->weFact->debtor($this->id)->update(
            $companyName, $initials, $surName, $emailAddress, $phoneNumber, $mobileNumber,
            $address, $zipCode, $city, $country, $companyNumber, $taxNumber, $comment, $attributes,
        );
    }

    /**
     * Put lines on the debtor's concept invoice. See `DebtorResource::bill()`.
     */
    public function bill(Line $line, Line ...$lines): Invoice
    {
        return $this->weFact->debtor($this->id)->bill($line, ...$lines);
    }
}
