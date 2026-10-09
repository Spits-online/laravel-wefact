<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Exceptions;

use RuntimeException;

/**
 * Every exception this package throws extends this one, so a single
 * `catch (WeFactException $e)` covers them all.
 */
class WeFactException extends RuntimeException {}
