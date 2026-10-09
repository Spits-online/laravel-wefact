<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Resources;

use Illuminate\Support\LazyCollection;
use SpitsOnline\WeFact\Concerns\Paginates;
use SpitsOnline\WeFact\Data\Product;
use SpitsOnline\WeFact\Exceptions\NotFound;
use SpitsOnline\WeFact\WeFact;

/**
 * The product catalogue: `WeFact::products()`.
 */
class Products
{
    use Paginates;

    public function __construct(
        protected WeFact $weFact,
    ) {}

    /**
     * Every product, fetched a page at a time as you iterate.
     *
     * @param  ?string  $search  matches the product code, name and key phrase
     * @return LazyCollection<int, Product>
     */
    public function get(?string $search = null): LazyCollection
    {
        return $this->paginate('product', 'products', ['searchfor' => $search])
            ->map(fn (array $product) => Product::fromArray($product));
    }

    /**
     * The product with this id, or null when it doesn't exist.
     */
    public function find(int $id): ?Product
    {
        return $this->show(['Identifier' => $id]);
    }

    /**
     * The product with this product code (`P001`), or null when it doesn't exist.
     */
    public function findByCode(string $code): ?Product
    {
        return $this->show(['ProductCode' => $code]);
    }

    /**
     * @param  array<string, int|string>  $key
     */
    protected function show(array $key): ?Product
    {
        try {
            return Product::fromArray($this->weFact->record('product', 'show', $key));
        } catch (NotFound) {
            return null;
        }
    }
}
