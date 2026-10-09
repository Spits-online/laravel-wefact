<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Resources;

use SpitsOnline\WeFact\Data\Debtor;
use SpitsOnline\WeFact\Exceptions\NotFound;
use SpitsOnline\WeFact\WeFact;

/**
 * One debtor: `WeFact::debtor($id)`. Picking a debtor sends no request; every method
 * after it sends exactly one. A `Debtor` you fetched has the same methods.
 */
class DebtorResource
{
    public function __construct(
        protected WeFact $weFact,
        protected int $id,
    ) {}

    /**
     * The debtor, with every field.
     *
     * @throws NotFound when it doesn't exist
     */
    public function get(): Debtor
    {
        return Debtor::fromArray($this->weFact->record('debtor', 'show', ['Identifier' => $this->id]), $this->weFact);
    }

    /**
     * Change the debtor and return it as the API saved it. Only the arguments you pass
     * are changed; pass `''` to clear a field.
     *
     * @param  array<string, mixed>  $attributes  any other debtor field the API documents, in its own keys
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
    ): Debtor {
        $fields = Debtors::fields($companyName, $initials, $surName, $emailAddress, $phoneNumber, $mobileNumber, $address, $zipCode, $city, $country, $companyNumber, $taxNumber, $comment);

        return Debtor::fromArray($this->weFact->record('debtor', 'edit', ['Identifier' => $this->id] + $fields + $attributes), $this->weFact);
    }
}
