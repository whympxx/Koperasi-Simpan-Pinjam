<?php

namespace Tests;

use App\Services\Settings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Cache pengaturan bersifat statis; kosongkan agar tiap test mulai bersih.
        Settings::flush();
    }
}
