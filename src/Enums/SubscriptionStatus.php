<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Enums;

enum SubscriptionStatus: string
{
    case ACTIVE = 'active';
    case TERMINATED = 'terminated';
}
