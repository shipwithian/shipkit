<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;
use ShipKit\Core\AccessControl\BootstrapAccessControlAction;

class AccessControlSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(BootstrapAccessControlAction $bootstrap): void
    {
        $bootstrap->handle(
            $this->credential('name', 'Super Admin'),
            $this->credential('email', 'admin@example.com'),
            $this->credential('password', 'password'),
        );
    }

    private function credential(string $key, string $localDefault): string
    {
        $value = config("access_control.super_admin.{$key}");

        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (app()->isProduction()) {
            throw new RuntimeException('SUPER_ADMIN_'.strtoupper($key).' must be configured in production.');
        }

        return $localDefault;
    }
}
