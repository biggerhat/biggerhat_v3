<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Models delete their saved images from the public disk (custom cards, art), and generators write to it, so
        // a test must never run against the real disk — it would wipe a developer's local files. Tests that need
        // their own fake simply call Storage::fake() again.
        Storage::fake('public');
    }
}
