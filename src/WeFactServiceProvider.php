<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class WeFactServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('wefact')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->mergeConfigRecursively();

        $this->app->singleton(WeFact::class, fn () => WeFact::fromConfig(Config::array('wefact')));
    }

    /**
     * Laravel merges a package config one level deep. Merging the package file
     * underneath again, key by key, lets the app's `config/wefact.php` state only
     * what differs. The config cache already holds the merged result.
     */
    private function mergeConfigRecursively(): void
    {
        if ($this->app->configurationIsCached()) {
            return;
        }

        Config::set('wefact', self::merge(Arr::wrap(require __DIR__.'/../config/wefact.php'), Config::array('wefact', [])));
    }

    /**
     * Associative arrays merge recursively, lists are replaced wholesale.
     *
     * @param  array<array-key, mixed>  $base
     * @param  array<array-key, mixed>  $override
     * @return array<array-key, mixed>
     */
    private static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            $base[$key] = is_array($value) && Arr::isAssoc($value) && is_array($base[$key] ?? null) && Arr::isAssoc($base[$key])
                ? self::merge($base[$key], $value)
                : $value;
        }

        return $base;
    }
}
