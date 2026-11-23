<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * RN04 · Cada organizador só vê e altera as próprias oficinas e as inscrições delas.
 */
class OrganizerOwnershipTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_new_course_belongs_to_the_organizer_who_created_it(): void
    {
        $organizer = User::factory()->create();

        $this->actingAs($organizer)->post(route('admin.events.store'), [
            'title' => 'Oficina de Patchwork',
            'description' => 'Primeiros blocos.',
            'price_reais' => '90,00',
            'status' => 'draft',
        ])->assertRedirect(route('admin.events.index'));

        $this->assertSame($organizer->id, Event::firstOrFail()->user_id);
    }

    public function test_course_list_shows_only_the_organizers_own_courses(): void
    {
        $ana = User::factory()->create();
        $bruno = User::factory()->create();
        Event::factory()->for($ana)->create(['title' => 'Bordado da Ana']);
        Event::factory()->for($bruno)->create(['title' => 'Crochê do Bruno']);

        $this->actingAs($ana)->get(route('admin.events.index'))
            ->assertOk()
            ->assertSee('Bordado da Ana')
            ->assertDontSee('Crochê do Bruno');
    }

    public function test_organizer_cannot_open_edit_or_delete_another_organizers_course(): void
    {
        $intruder = User::factory()->create();
        $event = Event::factory()->create(['title' => 'Oficina alheia']);

        $this->actingAs($intruder);
        $this->get(route('admin.events.edit', $event))->assertForbidden();
        $this->put(route('admin.events.update', $event), [
            'title' => 'Tentativa de alteração',
            'description' => 'x',
            'price_reais' => '1,00',
            'status' => 'published',
        ])->assertForbidden();
        $this->delete(route('admin.events.destroy', $event))->assertForbidden();

        $this->assertSame('Oficina alheia', $event->refresh()->title);
    }

    public function test_organizer_cannot_see_or_change_registrations_of_another_organizer(): void
    {
        $intruder = User::factory()->create();
        $registration = Registration::factory()->create([
            'participant_name' => 'Participante Protegida',
            'payment_method' => 'cash',
        ]);

        $this->actingAs($intruder);
        $this->get(route('admin.registrations.index'))->assertDontSee('Participante Protegida');
        $this->get(route('admin.registrations.export'))->assertDontSee('Participante Protegida');
        $this->get(route('admin.registrations.show', $registration))->assertForbidden();
        $this->patch(route('admin.registrations.payment.update', $registration), ['payment_received' => '1'])->assertForbidden();
        $this->delete(route('admin.registrations.destroy', $registration))->assertForbidden();

        $this->assertFalse($registration->refresh()->payment_received);
        $this->assertModelExists($registration);
    }
}
