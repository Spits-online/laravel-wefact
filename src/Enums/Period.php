<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Enums;

/**
 * How often a line, product or subscription is billed. A one-off price has no
 * period: its `period` is null.
 */
enum Period: string
{
    case DAY = 'd';
    case WEEK = 'w';
    case MONTH = 'm';
    case QUARTER = 'k';
    case HALF_YEAR = 'h';
    case YEAR = 'j';
    case TWO_YEARS = 't';
}
