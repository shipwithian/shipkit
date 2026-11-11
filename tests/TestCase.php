<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tests assert on Inertia props, not rendered assets, so they must not depend on a built Vite manifest.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->ensurePassportKeysExist();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * Generate Passport's signing keys once, when a checkout or CI run has none yet.
     */
    private function ensurePassportKeysExist(): void
    {
        if (! is_file(storage_path('oauth-private.key')) && config('passport.private_key') === null) {
            Artisan::call('passport:keys', ['--no-interaction' => true]);
        }
    }
}
