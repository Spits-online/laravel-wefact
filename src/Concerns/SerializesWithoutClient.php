<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Concerns;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use SpitsOnline\WeFact\WeFact;

/**
 * Leaves the client out when the object is serialized, so a queued job never stores
 * the API key. An unserialized object acts through the configured client.
 */
trait SerializesWithoutClient
{
    /**
     * @return array<array-key, mixed>
     */
    public function __serialize(): array
    {
        return Arr::except(get_object_vars($this), 'weFact');
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public function __unserialize(array $data): void
    {
        foreach ($data as $property => $value) {
            $this->{$property} = $value;
        }

        $this->weFact = App::make(WeFact::class);
    }
}
