<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Las vistas usan @vite: los tests no dependen de que los assets estén compilados.
        $this->withoutVite();
    }
}
