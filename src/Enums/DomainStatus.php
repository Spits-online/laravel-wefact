<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Enums;

enum DomainStatus: int
{
    case IN_ORDER = -1;
    case WAITING = 1;
    case REQUESTING = 3;
    case ACTIVE = 4;
    case EXPIRED = 5;
    case IN_PROGRESS = 6;
    case ERROR = 7;
    case CANCELLED = 8;
    case DELETED = 9;
}
