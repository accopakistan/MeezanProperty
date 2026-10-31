<?php

namespace Tests;

use App\Support\Lookup;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Lookup caches per request; reset it between tests.
        Lookup::flush();
        $this->withoutVite();
    }
}
