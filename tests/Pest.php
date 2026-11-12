<?php

use Dcplibrary\Notices\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Pest Test Case
|--------------------------------------------------------------------------
|
| Closure-based Pest tests use the package TestCase by default. Existing
| class-based PHPUnit tests continue to run unchanged during migration.
|
*/

uses(TestCase::class)->in('Feature', 'Unit');
