<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Run DatabaseSeeder (game content: ingredients + cans) for every
     * RefreshDatabase test — registration grants a starter can that needs it.
     */
    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Treat every test request as coming from the first-party SPA so
        // Sanctum runs the stateful session + CSRF stack (matches
        // SANCTUM_STATEFUL_DOMAINS=localhost in phpunit.xml).
        $this->withHeader('Origin', 'http://localhost');
    }
}
