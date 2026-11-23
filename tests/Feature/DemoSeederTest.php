<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_demo_data_is_split_between_two_organizers_and_can_be_seeded_twice(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(2, User::count());
        $this->assertSame(4, Event::count());
        $this->assertSame(10, Registration::count());

        $loja = User::where('username', 'loja.demo')->firstOrFail();
        $this->actingAs($loja)->get(route('admin.events.index'))
            ->assertSee('Bordado livre para iniciantes')
            ->assertDontSee('Patchwork: almofada em blocos');
    }

    public function test_full_course_from_demo_data_refuses_new_registrations(): void
    {
        $this->seed(DemoSeeder::class);

        $full = Event::where('title', 'Macramê: suporte de planta')->withCount('occupiedRegistrations')->firstOrFail();

        $this->assertFalse($full->acceptsNewRegistrations());
    }
}
