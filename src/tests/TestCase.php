<?php

namespace Dcplibrary\notices\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Dcplibrary\notices\App\Providers\noticesServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }
    
    protected function getPackageProviders($app): array
    {
        return [
            noticesServiceProvider::class,
        ];
    }
}
