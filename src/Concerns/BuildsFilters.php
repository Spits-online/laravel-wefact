<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Concerns;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Support\Arr;
use SpitsOnline\WeFact\Data\Debtor;

trait BuildsFilters
{
    use WritesPayload;

    /**
     * The `list` filters for one debtor's records. Searching the `Debtor` field matches
     * the debtor id exactly, so debtor 1 doesn't also match debtor 12.
     *
     * @return array<string, int|string>
     */
    protected static function ofDebtor(Debtor|int|null $debtor): array
    {
        return $debtor === null ? [] : [
            'searchat' => 'Debtor',
            'searchfor' => $debtor instanceof Debtor ? $debtor->id : $debtor,
        ];
    }

    /**
     * One status, or several (`[QuoteStatus::SENT, QuoteStatus::ACCEPTED]`), as the API
     * takes them: `2|3`.
     *
     * @param  BackedEnum|list<BackedEnum>|null  $status
     */
    protected static function status(BackedEnum|array|null $status): ?string
    {
        $statuses = collect(Arr::wrap($status))->map(fn (BackedEnum $case) => $case->value);

        return $statuses->isEmpty() ? null : $statuses->implode('|');
    }

    /**
     * @return array<string, array{from: string}>
     */
    protected static function modifiedSince(?DateTimeInterface $since): array
    {
        return $since === null ? [] : ['modified' => ['from' => (string) self::moment($since)]];
    }
}
