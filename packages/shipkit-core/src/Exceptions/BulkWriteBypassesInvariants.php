<?php

namespace ShipKit\Core\Exceptions;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * A bulk query-builder write was attempted on a guarded aggregate.
 *
 * This is a programming error, not a business failure, so it is not a {@see DomainException}.
 */
class BulkWriteBypassesInvariants extends LogicException
{
    public static function for(Model $model, string $operation): self
    {
        $class = class_basename($model);

        return new self("Bulk {$operation} on {$class} bypasses its invariant guards. Create, save, or delete each record through the model instead.");
    }
}
