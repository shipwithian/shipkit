<?php

namespace ShipKit\Core\Exceptions;

use LogicException;

/**
 * A model mapped in `config/shipkit.php` is missing, absent, or unguarded.
 *
 * This is a programming error, not a business failure, so it is not a {@see DomainException}.
 */
class GuardedModelIsMisconfigured extends LogicException
{
    public static function unmapped(string $key): self
    {
        return new self("No model is mapped to [shipkit.models.{$key}]. Map the application's model in config/shipkit.php.");
    }

    public static function missing(string $key, string $class): self
    {
        return new self("The model [{$class}] mapped to [shipkit.models.{$key}] does not exist.");
    }

    public static function unguarded(string $key, string $class): self
    {
        return new self("The model [{$class}] mapped to [shipkit.models.{$key}] does not use GuardsInvariants, so its invariants could be bypassed. Add `use GuardsInvariants;` to it.");
    }
}
