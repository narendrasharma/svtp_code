<?php

namespace Tests;

use App\Support\DatabaseSafety;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Abort immediately if the test suite is pointed at an active
        // database (e.g. tour_canyon_new). Tests may only use an
        // isolated database such as sqlite :memory:.
        DatabaseSafety::assertSafeTestDatabase();
    }
}
