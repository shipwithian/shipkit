<?php

namespace ShipKit\Core;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use ShipKit\Core\Eloquent\GuardsInvariants;
use ShipKit\Core\Exceptions\GuardedModelIsMisconfigured;

/**
 * The application's guarded Role, Permission, and User models, read from `config/shipkit.php`.
 *
 * ShipKit reads application models from here so it never assumes their class names.
 */
class GuardedModelMap
{
    /**
     * @var list<string>
     */
    public const array KEYS = ['role', 'permission', 'user'];

    public function __construct(private readonly Repository $config) {}

    /**
     * @return class-string<Model>
     */
    public function role(): string
    {
        return $this->resolve('role');
    }

    /**
     * @return class-string<Model>
     */
    public function permission(): string
    {
        return $this->resolve('permission');
    }

    /**
     * @return class-string<Model>
     */
    public function user(): string
    {
        return $this->resolve('user');
    }

    /**
     * Throw when a mapped model does not exist or does not use GuardsInvariants. Unmapped keys are
     * left for `shipkit:verify` to report, so an unpublished config does not stop the app booting.
     *
     * @throws GuardedModelIsMisconfigured
     */
    public function ensureMappedModelsAreGuarded(): void
    {
        foreach (self::KEYS as $key) {
            if ($this->mappedClass($key) !== null) {
                $this->resolve($key);
            }
        }
    }

    /**
     * Every misconfiguration, for reporting without throwing.
     *
     * @return list<string>
     */
    public function misconfigurations(): array
    {
        $misconfigurations = [];

        foreach (self::KEYS as $key) {
            try {
                $this->resolve($key);
            } catch (GuardedModelIsMisconfigured $exception) {
                $misconfigurations[] = $exception->getMessage();
            }
        }

        return $misconfigurations;
    }

    /**
     * @return class-string<Model>
     *
     * @throws GuardedModelIsMisconfigured
     */
    private function resolve(string $key): string
    {
        $class = $this->mappedClass($key) ?? throw GuardedModelIsMisconfigured::unmapped($key);

        if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            throw GuardedModelIsMisconfigured::missing($key, $class);
        }

        if (! in_array(GuardsInvariants::class, class_uses_recursive($class), true)) {
            throw GuardedModelIsMisconfigured::unguarded($key, $class);
        }

        return $class;
    }

    private function mappedClass(string $key): ?string
    {
        $class = $this->config->get("shipkit.models.{$key}");

        return is_string($class) && $class !== '' ? $class : null;
    }
}
