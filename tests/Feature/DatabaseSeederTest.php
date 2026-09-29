<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_default_seeder_does_not_create_a_predictable_administrator(): void
    {
        $this->seed();

        $this->assertDatabaseCount('users', 0);
    }
}
