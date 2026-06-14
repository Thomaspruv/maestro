<?php

namespace Tests;

use App\Support\ProtectDevDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ProtectDevDatabase::enforceTestingConnection();
        $this->setUpTestingEnvironment();
    }

    private function setUpTestingEnvironment(): void
    {
        foreach (ProtectDevDatabase::testingProcessEnvironment() as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}
