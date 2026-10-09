<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Enums;

enum ProductType: string
{
    case OTHER = 'other';
    case DOMAIN = 'domain';
    case HOSTING = 'hosting';
    case SSL = 'ssl';
    case VPS = 'vps';
}
