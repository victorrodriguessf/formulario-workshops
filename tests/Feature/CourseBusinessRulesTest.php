<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Regras de negócio do cadastro de oficinas e do resumo de inscrições.
 */
class CourseBusinessRulesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function validCourse(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Oficina de Bordado',
            'description' => 'Aprenda pontos básicos.',
            'price_reais' => '85,00',
            'status' => 'published',
        ], $overrides);
    }

    public function test_registrations_must_close_before_the_course_starts(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.events.store'), $this->validCourse([
            'starts_at' => '2026-12-10T14:00',
            'registrations_close_at' => '2026-12-11T14:00',
        ]))->assertSessionHasErrors([
            'registrations_close_at' => 'As inscrições devem encerrar até o início da oficina.',
        ]);

        $this->assertDatabaseCount('events', 0);
    }

    public function test_capacity_cannot_be_lower_than_active_registrations(): void
    {
        $event = Event::factory()->create(['capacity' => 5]);
        Registration::factory()->count(3)->create(['event_id' => $event->id]);
        Registration::factory()->create(['event_id' => $event->id, 'status' => 'cancelled']);
        $this->actingAs($event->user);

        $this->put(route('admin.events.update', $event), $this->validCourse(['capacity' => '2']))
            ->assertSessionHasErrors([
                'capacity' => 'O limite não pode ser menor que as 3 inscrições ativas desta oficina.',
            ]);
        $this->assertSame(5, $event->refresh()->capacity);

        $this->put(route('admin.events.update', $event), $this->validCourse(['capacity' => '3']))
            ->assertSessionHasNoErrors();
        $this->assertSame(3, $event->refresh()->capacity);
    }

    public function test_course_with_paid_registrations_cannot_be_deleted(): void
    {
        $event = Event::factory()->create();
        $paid = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => 'confirmed',
            'payment_received' => true,
        ]);
        $this->actingAs($event->user);

        $this->get(route('admin.events.index'))
            ->assertSee('Tem pagamentos recebidos');
        $this->delete(route('admin.events.destroy', $event))
            ->assertRedirect(route('admin.events.index'))
            ->assertSessionHasErrors(['event' => 'Esta oficina tem inscrições pagas. Desative-a (cancele ou pause) em vez de excluir.']);

        $this->assertModelExists($event);
        $this->assertModelExists($paid);
    }

    public function test_summary_follows_the_filters_and_counts_only_received_payments(): void
    {
        $organizer = User::factory()->create();
        $bordado = Event::factory()->for($organizer)->create(['title' => 'Bordado', 'capacity' => 10]);
        $croche = Event::factory()->for($organizer)->create(['title' => 'Crochê']);
        $outroOrganizador = Event::factory()->create();

        Registration::factory()->count(2)->create(['event_id' => $bordado->id, 'status' => 'confirmed', 'payment_received' => true, 'agreed_price_cents' => 8500]);
        Registration::factory()->create(['event_id' => $bordado->id, 'status' => 'awaiting_payment', 'agreed_price_cents' => 8500]);
        Registration::factory()->create(['event_id' => $croche->id, 'status' => 'confirmed', 'payment_received' => true, 'agreed_price_cents' => 6000]);
        Registration::factory()->create(['event_id' => $outroOrganizador->id, 'status' => 'confirmed', 'payment_received' => true, 'agreed_price_cents' => 99900]);
        $this->actingAs($organizer);

        $this->get(route('admin.registrations.index'))
            ->assertViewHas('summary', fn (array $summary) => $summary['total'] === 4
                && $summary['confirmed'] === 3
                && $summary['awaiting_payment'] === 1
                && $summary['revenue_cents'] === 23000
                && $summary['spots_left'] === null)
            ->assertSee('R$ 230,00');

        $this->get(route('admin.registrations.index', ['event_id' => $bordado->id]))
            ->assertViewHas('summary', fn (array $summary) => $summary['total'] === 3
                && $summary['revenue_cents'] === 17000
                && $summary['spots_left'] === 7)
            ->assertSee('Vagas restantes nesta oficina');
    }
}
