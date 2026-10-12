<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Тесты не зависят от собранного фронтенда (public/build), в том числе в CI
        $this->withoutVite();
    }
}
