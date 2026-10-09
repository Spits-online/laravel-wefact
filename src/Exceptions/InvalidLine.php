<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Exceptions;

final class InvalidLine extends WeFactException
{
    public static function empty(): self
    {
        return new self('A line needs a description or a product code.');
    }
}
