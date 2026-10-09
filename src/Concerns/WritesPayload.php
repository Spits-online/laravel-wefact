<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Concerns;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Writes values the way the API accepts them: a dot as the decimal separator and no
 * thousands separator (`1250.5`, never `1,250.50`, which the API rejects), and dates
 * in the API's timezone.
 */
trait WritesPayload
{
    use UsesApiTimezone;

    protected static function decimal(?float $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Not Number::format(): it adds the locale's thousands separator.
        return Str::of(number_format($value, 6, '.', ''))->rtrim('0')->rtrim('.')->value();
    }

    protected static function day(?DateTimeInterface $date): ?string
    {
        return $date === null ? null : Carbon::instance($date)->setTimezone(self::apiTimezone())->toDateString();
    }

    protected static function moment(?DateTimeInterface $date): ?string
    {
        return $date === null ? null : Carbon::instance($date)->setTimezone(self::apiTimezone())->toDateTimeString();
    }
}
