<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Enums;

/**
 * The status of a quote ("offerte"). Archiving a quote doesn't change its status;
 * an archived quote just stops appearing in lists.
 */
enum QuoteStatus: int
{
    case CONCEPT = 0;
    case QUEUED = 1;
    case SENT = 2;
    case ACCEPTED = 3;
    case INVOICED = 4;
    case DECLINED = 8;

    /**
     * Whether the customer accepted the quote, including when it became an invoice.
     */
    public function isAccepted(): bool
    {
        return match ($this) {
            self::ACCEPTED, self::INVOICED => true,
            default => false,
        };
    }
}
