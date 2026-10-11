<?php

namespace Tests;

use App\Services\MultiBranch\Install;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Install::forget(); // installation mode is cached per process
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Install::forget();
    }
}
