<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests (app + every module) run on the Laravel TestCase with a
| fresh in-memory database. Module unit tests that need the container
| opt in per file with pest()->extend(TestCase::class).
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', '../modules/*/Tests/Feature');

pest()->extend(TestCase::class)
    ->in('Unit', '../modules/*/Tests/Unit');
