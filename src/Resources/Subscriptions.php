<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Resources;

use Illuminate\Support\LazyCollection;
use SpitsOnline\WeFact\Concerns\BuildsFilters;
use SpitsOnline\WeFact\Concerns\Paginates;
use SpitsOnline\WeFact\Data\Debtor;
use SpitsOnline\WeFact\Data\Subscription;
use SpitsOnline\WeFact\Enums\SubscriptionStatus;
use SpitsOnline\WeFact\WeFact;

/**
 * Every subscription: `WeFact::subscriptions()`.
 */
class Subscriptions
{
    use BuildsFilters;
    use Paginates;

    public function __construct(
        protected WeFact $weFact,
    ) {}

    /**
     * Every subscription, fetched a page at a time as you iterate. Like the API, this
     * returns the active ones unless you ask for another status; `status: null`
     * returns them all.
     *
     * @return LazyCollection<int, Subscription>
     */
    public function get(?SubscriptionStatus $status = SubscriptionStatus::ACTIVE, Debtor|int|null $debtor = null): LazyCollection
    {
        // An empty status is how the API is asked for every status.
        return $this->paginate('subscription', 'subscriptions', ['status' => $status->value ?? ''] + self::ofDebtor($debtor))
            ->map(fn (array $subscription) => Subscription::fromArray($subscription));
    }
}
