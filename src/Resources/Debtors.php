<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Resources;

use DateTimeInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\LazyCollection;
use SpitsOnline\WeFact\Concerns\BuildsFilters;
use SpitsOnline\WeFact\Concerns\Paginates;
use SpitsOnline\WeFact\Data\Debtor;
use SpitsOnline\WeFact\Exceptions\NotFound;
use SpitsOnline\WeFact\WeFact;

/**
 * Every debtor: `WeFact::debtors()`. To work with one debtor, use `WeFact::debtor($id)`,
 * or the `Debtor` that `find()` returns.
 */
class Debtors
{
    use BuildsFilters;
    use Paginates;

    public function __construct(
        protected WeFact $weFact,
    ) {}

    /**
     * Every debtor, fetched a page at a time as you iterate. These are the API's short
     * debtors: see `Debtor` for the fields they hold.
     *
     * @param  ?string  $search  matches the debtor code, company name and surname
     * @param  ?DateTimeInterface  $modifiedSince  only debtors changed at or after this moment
     * @return LazyCollection<int, Debtor>
     */
    public function get(?string $search = null, ?DateTimeInterface $modifiedSince = null): LazyCollection
    {
        return $this->paginate('debtor', 'debtors', ['searchfor' => $search] + self::modifiedSince($modifiedSince))
            ->map(fn (array $debtor) => Debtor::fromArray($debtor, $this->weFact));
    }

    /**
     * The debtor with this id, with every field, or null when it doesn't exist.
     */
    public function find(int $id): ?Debtor
    {
        return $this->show(['Identifier' => $id]);
    }

    /**
     * The debtor with this debtor code (`DB10001`), with every field, or null when it
     * doesn't exist. One request: the API takes the code directly.
     */
    public function findByCode(string $code): ?Debtor
    {
        return $this->show(['DebtorCode' => $code]);
    }

    /**
     * Create a debtor and return it as the API saved it, with its new id and code.
     *
     * @param  array<string, mixed>  $attributes  any other debtor field the API documents, in its own keys
     */
    public function create(
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
    ): Debtor {
        $fields = self::fields($companyName, $initials, $surName, $emailAddress, $phoneNumber, $mobileNumber, $address, $zipCode, $city, $country, $companyNumber, $taxNumber, $comment);

        return Debtor::fromArray($this->weFact->record('debtor', 'add', $fields + $attributes), $this->weFact);
    }

    /**
     * The debtor fields in the API's keys, leaving out the ones not passed.
     *
     * @return array<string, string>
     *
     * @internal
     */
    public static function fields(
        ?string $companyName,
        ?string $initials,
        ?string $surName,
        ?string $emailAddress,
        ?string $phoneNumber,
        ?string $mobileNumber,
        ?string $address,
        ?string $zipCode,
        ?string $city,
        ?string $country,
        ?string $companyNumber,
        ?string $taxNumber,
        ?string $comment,
    ): array {
        return Arr::whereNotNull([
            'CompanyName' => $companyName,
            'Initials' => $initials,
            'SurName' => $surName,
            'EmailAddress' => $emailAddress,
            'PhoneNumber' => $phoneNumber,
            'MobileNumber' => $mobileNumber,
            'Address' => $address,
            'ZipCode' => $zipCode,
            'City' => $city,
            'Country' => $country,
            'CompanyNumber' => $companyNumber,
            'TaxNumber' => $taxNumber,
            'Comment' => $comment,
        ]);
    }

    /**
     * @param  array<string, int|string>  $key
     */
    protected function show(array $key): ?Debtor
    {
        try {
            return Debtor::fromArray($this->weFact->record('debtor', 'show', $key), $this->weFact);
        } catch (NotFound) {
            return null;
        }
    }
}
