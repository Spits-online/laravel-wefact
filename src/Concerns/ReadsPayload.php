<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Concerns;

use BackedEnum;
use Carbon\CarbonImmutable;
use Illuminate\Support\Fluent;

/**
 * Reads the API's answers, which send almost every value as a string (`"Identifier": "3"`,
 * `"PriceExcl": "500"`) and an empty string for a missing one.
 */
trait ReadsPayload
{
    use UsesApiTimezone;

    /**
     * The trimmed text, or null when it is empty.
     *
     * @param  Fluent<array-key, mixed>  $data
     */
    protected static function text(Fluent $data, string $key): ?string
    {
        $value = $data->string($key)->trim()->value();

        return filled($value) ? $value : null;
    }

    /**
     * @param  Fluent<array-key, mixed>  $data
     */
    protected static function number(Fluent $data, string $key, float $default = 0.0): float
    {
        return self::optionalNumber($data, $key) ?? $default;
    }

    /**
     * @param  Fluent<array-key, mixed>  $data
     */
    protected static function optionalNumber(Fluent $data, string $key): ?float
    {
        return is_numeric($data->get($key)) ? $data->float($key) : null;
    }

    /**
     * A date or date and time, read in the API's timezone. The API's "no date" values
     * (`''`, `0000-00-00`) read as null.
     *
     * @param  Fluent<array-key, mixed>  $data
     */
    protected static function date(Fluent $data, string $key): ?CarbonImmutable
    {
        if ($data->string($key)->startsWith('0000-00-00')) {
            return null;
        }

        return rescue(fn () => $data->date($key, tz: self::apiTimezone())?->toImmutable(), report: false);
    }

    /**
     * The enum case for the value, or null when the API sends a value this package
     * doesn't know. `$raw` still holds what the API sent.
     *
     * @template TEnum of BackedEnum
     *
     * @param  Fluent<array-key, mixed>  $data
     * @param  class-string<TEnum>  $enum
     * @return ?TEnum
     */
    protected static function enum(Fluent $data, string $key, string $enum): ?BackedEnum
    {
        return rescue(fn () => $data->enum($key, $enum), report: false);
    }
}
