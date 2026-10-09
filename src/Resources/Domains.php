<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Resources;

use Illuminate\Support\LazyCollection;
use SpitsOnline\WeFact\Concerns\BuildsFilters;
use SpitsOnline\WeFact\Concerns\Paginates;
use SpitsOnline\WeFact\Data\Debtor;
use SpitsOnline\WeFact\Data\Domain;
use SpitsOnline\WeFact\Enums\DomainStatus;
use SpitsOnline\WeFact\Exceptions\NotFound;
use SpitsOnline\WeFact\WeFact;

/**
 * Every domain HostFact manages: `WeFact::domains()`, on the HostFact driver only.
 */
class Domains
{
    use BuildsFilters;
    use Paginates;

    public function __construct(
        protected WeFact $weFact,
    ) {}

    /**
     * Every domain, sorted by name, fetched a page at a time as you iterate.
     *
     * @param  DomainStatus|list<DomainStatus>|null  $status
     * @return LazyCollection<int, Domain>
     */
    public function get(DomainStatus|array|null $status = null, Debtor|int|null $debtor = null): LazyCollection
    {
        return $this->paginate('domain', 'domains', ['status' => self::status($status)] + self::ofDebtor($debtor))
            ->map(fn (array $domain) => Domain::fromArray($domain));
    }

    /**
     * The domain with this id, or null when it doesn't exist.
     */
    public function find(int $id): ?Domain
    {
        try {
            return Domain::fromArray($this->weFact->record('domain', 'show', ['Identifier' => $id]));
        } catch (NotFound) {
            return null;
        }
    }
}
