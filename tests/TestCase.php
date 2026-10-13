<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Existing suites exercise production-compatible identifiers.
        config(['wallet.test_identity_namespace' => '']);
    }
}
