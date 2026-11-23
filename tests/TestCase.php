<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Os testes verificam o HTML e as regras; não precisam do CSS/JS compilado.
        $this->withoutVite();
    }
}
