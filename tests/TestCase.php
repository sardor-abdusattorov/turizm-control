<?php

namespace Tests;

use Database\Seeders\HandEnteredContractsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        HandEnteredContractsSeeder::$path = storage_path('framework/testing/no-snapshot.json');
    }
}
