<?php

namespace Tests;

use Database\Seeders\HandEnteredContractsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The committed production snapshot must never leak into tests —
        // every DatabaseSeeder run sees a missing file (a no-op) unless a
        // test deliberately points the seeder at its own scratch snapshot.
        HandEnteredContractsSeeder::$path = storage_path('framework/testing/no-snapshot.json');
    }
}
