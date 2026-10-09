<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Enums;

enum InvoiceStatus: int
{
    case CONCEPT = 0;
    case QUEUED = 1;
    case SENT = 2;
    case PARTLY_PAID = 3;
    case PAID = 4;
    case CREDIT_INVOICE = 8;
    case EXPIRED = 9;
}
