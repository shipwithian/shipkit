<?php

namespace ShipKit\Core\Exceptions;

use RuntimeException;

/**
 * A business rule was violated.
 *
 * Rendered as a validation error on {@see field()} at the HTTP boundary.
 */
abstract class DomainException extends RuntimeException
{
    /**
     * The input or resource key the failure is reported against.
     */
    abstract public function field(): string;
}
