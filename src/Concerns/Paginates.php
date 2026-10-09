<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Concerns;

use Illuminate\Support\Arr;
use Illuminate\Support\Fluent;
use Illuminate\Support\LazyCollection;
use SpitsOnline\WeFact\WeFact;

/**
 * @property-read WeFact $weFact
 */
trait Paginates
{
    /**
     * The API's own default and the most it returns at once.
     */
    protected const int PAGE_SIZE = 1000;

    /**
     * Every row of a `list` action, fetched a page at a time as you iterate.
     *
     * The API answers a page past the end with `totalresults: 0` and no rows at all, so
     * paging stops on a short page instead of trusting the total alone.
     *
     * @param  array<string, mixed>  $filters
     * @return LazyCollection<int, array<array-key, mixed>>
     */
    protected function paginate(string $controller, string $key, array $filters): LazyCollection
    {
        return LazyCollection::make(function () use ($controller, $key, $filters) {
            $offset = 0;

            do {
                $body = new Fluent($this->weFact->request($controller, 'list', [
                    ...Arr::whereNotNull($filters),
                    'offset' => $offset,
                    'limit' => static::PAGE_SIZE,
                ]));

                $rows = $body->collect($key)->filter(fn (mixed $row) => is_array($row))->values();

                // Not `yield from`: that would restart the keys at 0 on every page.
                foreach ($rows as $row) {
                    yield $row;
                }

                $offset += $rows->count();
            } while ($rows->count() === static::PAGE_SIZE && $offset < $body->integer('totalresults'));
        });
    }
}
