<?php

namespace Tests\Feature;

use App\Mail\CashRegistrationReserved;
use App\Mail\RegistrationConfirmed;
use App\Models\Event;
use App\Models\Registration;
use App\Services\Payment\PaymentGatewayInterface;
use App\Services\Payment\PaymentRequestRejected;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EventRegistrationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function participant(): array
    {
        return [
            'participant_name' => 'Maria da Silva',
            'participant_whatsapp' => '(84) 99999-9999',
            'participant_cpf' => '529.982.247-25',
            'participant_email' => 'maria@example.com',
            'payment_method' => 'pix',
        ];
    }

    public function test_home_lists_published_courses_only(): void
    {
        Event::factory()->create(['title' => 'Oficina de crochê']);
        Event::factory()->create(['title' => 'Curso escondido', 'status' => 'draft']);

        $this->get('/')->assertSee('Oficina de crochê')->assertDontSee('Curso escondido');
    }

    public function test_review_shows_correct_price_and_keeps_data_for_correction(): void
    {
        $event = Event::factory()->create(['price_cents' => 8500]);

        $response = $this->post(route('events.register.review', $event->id), $this->participant())
            ->assertSee('R$ 85,00')
            ->assertSee('Maria da Silva');

        $sessionData = session('registration_data_'.$event->id);
        $this->assertArrayNotHasKey('participant_cpf', $sessionData);
        $this->assertSame('529.982.247-25', Crypt::decryptString($sessionData['participant_cpf_encrypted']));
        $this->get(route('events.show', $event->id))->assertSee('maria@example.com');
    }

    public function test_rejects_invalid_cpf_without_creating_registration(): void
    {
        $event = Event::factory()->create();

        $this->from(route('events.show', $event->id))
            ->post(route('events.register.review', $event->id), [...$this->participant(), 'participant_cpf' => '111.111.111-11'])
            ->assertSessionHasErrors('participant_cpf');
        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_paused_course_keeps_page_visible_but_blocks_review(): void
    {
        $event = Event::factory()->create(['is_paused' => true]);

        $this->get(route('events.show', $event->id))->assertSee('Inscrições indisponíveis');
        $this->post(route('events.register.review', $event->id), $this->participant())->assertForbidden();
    }

    public function test_course_stops_accepting_registrations_when_capacity_is_reached(): void
    {
        $event = Event::factory()->create(['capacity' => 1]);
        Registration::factory()->create(['event_id' => $event->id]);

        $this->get(route('events.show', $event->id))
            ->assertSee('0 de 1 disponíveis')
            ->assertSee('As vagas desta oficina estão esgotadas.');
        $this->post(route('events.register.review', $event->id), $this->participant())->assertForbidden();
    }

    public function test_cancelled_registration_releases_its_spot(): void
    {
        $event = Event::factory()->create(['capacity' => 1]);
        Registration::factory()->create(['event_id' => $event->id, 'status' => 'cancelled']);

        $this->post(route('events.register.review', $event->id), $this->participant())
            ->assertSee('Confira seus dados');
    }

    public function test_cash_registration_stays_pending_without_creating_a_pix_attempt(): void
    {
        Mail::fake();
        $deadline = now()->addDays(3)->setSecond(0);
        $event = Event::factory()->create(['cash_payment_deadline' => $deadline]);
        $this->post(route('events.register.review', $event->id), [...$this->participant(), 'payment_method' => 'cash'])
            ->assertSee('Dinheiro, presencialmente');

        $this->post(route('events.register.process', $event->id));

        $registration = Registration::firstOrFail();
        $this->assertDatabaseHas('registrations', [
            'id' => $registration->id,
            'payment_method' => 'cash',
            'payment_received' => false,
            'status' => 'awaiting_payment',
            'cash_payment_due_at' => $deadline,
        ]);
        $this->assertDatabaseCount('payment_attempts', 0);
        $this->get(route('events.register.status', ['id' => $event->id, 'protocol' => $registration->protocol]))
            ->assertSee('Sua inscrição foi reservada com sucesso')
            ->assertSee($deadline->format('d/m/Y'));
        Mail::assertSent(CashRegistrationReserved::class, fn (CashRegistrationReserved $mail): bool => $mail->hasTo('maria@example.com'));
    }

    public function test_cash_is_hidden_and_rejected_without_a_future_deadline(): void
    {
        $event = Event::factory()->create(['cash_payment_deadline' => now()->subMinute()]);

        $this->get(route('events.show', $event->id))
            ->assertDontSee('Dinheiro, presencialmente');

        $this->from(route('events.show', $event->id))
            ->post(route('events.register.review', $event->id), [...$this->participant(), 'payment_method' => 'cash'])
            ->assertSessionHasErrors('payment_method');
    }

    public function test_changed_cash_deadline_requires_a_new_review(): void
    {
        $event = Event::factory()->create(['cash_payment_deadline' => now()->addDays(2)]);
        $this->post(route('events.register.review', $event->id), [...$this->participant(), 'payment_method' => 'cash']);
        $event->update(['cash_payment_deadline' => now()->addDays(3)]);

        $this->post(route('events.register.process', $event->id))
            ->assertRedirect(route('events.show', $event->id))
            ->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_overdue_cash_reservation_expires_and_releases_the_spot(): void
    {
        $event = Event::factory()->create(['capacity' => 1]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'payment_method' => 'cash',
            'cash_payment_due_at' => now()->subMinute(),
        ]);

        $this->artisan('registrations:expire-cash')->assertSuccessful();

        $this->assertSame('expired', $registration->refresh()->status);
        $this->get(route('events.show', $event->id))->assertSee('1 de 1 disponíveis');
    }

    public function test_changed_price_requires_a_new_review_before_pix(): void
    {
        $event = Event::factory()->create(['price_cents' => 8500]);
        $this->post(route('events.register.review', $event->id), $this->participant());
        $event->update(['price_cents' => 9000]);

        $this->post(route('events.register.process', $event->id))
            ->assertRedirect(route('events.show', $event->id))
            ->assertSessionHasErrors('registration');
        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_provider_rejection_allows_pix_to_be_retried(): void
    {
        $event = Event::factory()->create();
        $this->mock(PaymentGatewayInterface::class)
            ->shouldReceive('createPixPayment')
            ->once()
            ->andThrow(new PaymentRequestRejected('Rejected'));

        $this->post(route('events.register.review', $event->id), $this->participant());
        $this->post(route('events.register.process', $event->id));

        $registration = Registration::firstOrFail();
        $this->assertSame('failed', $registration->paymentAttempts()->firstOrFail()->local_status);
        $this->get(route('events.register.status', ['id' => $event->id, 'protocol' => $registration->protocol]))
            ->assertSee('Gerar novo Pix');
    }

    public function test_query_parameter_cannot_approve_a_pending_payment(): void
    {
        $event = Event::factory()->create();
        $this->post(route('events.register.review', $event->id), $this->participant());
        $this->post(route('events.register.process', $event->id));
        $registration = Registration::firstOrFail();

        $this->get(route('events.register.status', ['id' => $event->id, 'protocol' => $registration->protocol, 'payment_status' => 'success']))
            ->assertSee('Hora de pagar com Pix')
            ->assertDontSee('Sua inscrição está confirmada!');
        $this->assertDatabaseHas('registrations', ['id' => $registration->id, 'status' => 'awaiting_payment']);
    }

    public function test_payment_status_requires_the_registration_session(): void
    {
        $event = Event::factory()->create();
        $registration = Registration::factory()->create(['event_id' => $event->id]);

        $this->get(route('events.register.status', ['id' => $event->id, 'protocol' => $registration->protocol]))->assertNotFound();
    }

    public function test_provider_confirmation_marks_registration_paid(): void
    {
        Mail::fake();
        $event = Event::factory()->create();
        $this->post(route('events.register.review', $event->id), $this->participant());
        $this->post(route('events.register.process', $event->id));
        $registration = Registration::firstOrFail();
        $this->mock(PaymentGatewayInterface::class)->shouldReceive('checkPaymentStatus')->once()->andReturn(['status' => 'paid']);

        $this->get(route('events.register.status', ['id' => $event->id, 'protocol' => $registration->protocol]))
            ->assertSee('Sua inscrição está confirmada!');
        $this->get(route('events.register.status', ['id' => $event->id, 'protocol' => $registration->protocol]))
            ->assertSee('Sua inscrição está confirmada!');
        $this->assertDatabaseHas('registrations', ['id' => $registration->id, 'status' => 'confirmed', 'payment_received' => true]);
        Mail::assertSent(RegistrationConfirmed::class, 1);
    }

    public function test_pix_page_polls_for_payment_confirmation(): void
    {
        $event = Event::factory()->create();
        $this->post(route('events.register.review', $event->id), $this->participant());
        $this->post(route('events.register.process', $event->id));
        $registration = Registration::firstOrFail();

        $this->get(route('events.register.status', ['id' => $event->id, 'protocol' => $registration->protocol]))
            ->assertSee('data-payment-poll="'.route('events.register.status.check', ['id' => $event->id, 'protocol' => $registration->protocol]).'"', false)
            ->assertSee('data-payment-state="pending"', false);
    }

    public function test_status_check_reports_a_pending_pix(): void
    {
        $event = Event::factory()->create();
        $this->post(route('events.register.review', $event->id), $this->participant());
        $this->post(route('events.register.process', $event->id));
        $registration = Registration::firstOrFail();

        $this->getJson(route('events.register.status.check', ['id' => $event->id, 'protocol' => $registration->protocol]))
            ->assertOk()
            ->assertExactJson(['status' => 'awaiting_payment', 'payment' => 'pending'])
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_status_check_confirms_the_registration_when_the_provider_approves(): void
    {
        $event = Event::factory()->create();
        $this->post(route('events.register.review', $event->id), $this->participant());
        $this->post(route('events.register.process', $event->id));
        $registration = Registration::firstOrFail();
        $this->mock(PaymentGatewayInterface::class)->shouldReceive('checkPaymentStatus')->once()->andReturn(['status' => 'paid']);

        $this->getJson(route('events.register.status.check', ['id' => $event->id, 'protocol' => $registration->protocol]))
            ->assertExactJson(['status' => 'confirmed', 'payment' => 'paid']);
        $this->assertDatabaseHas('registrations', ['id' => $registration->id, 'status' => 'confirmed', 'payment_received' => true]);
    }

    public function test_status_check_requires_the_registration_session(): void
    {
        $event = Event::factory()->create();
        $registration = Registration::factory()->create(['event_id' => $event->id]);

        $this->getJson(route('events.register.status.check', ['id' => $event->id, 'protocol' => $registration->protocol]))->assertNotFound();
    }

    public function test_failed_pix_can_be_retried_without_a_second_registration(): void
    {
        $event = Event::factory()->create(['capacity' => 1]);
        $this->post(route('events.register.review', $event->id), $this->participant());
        $this->post(route('events.register.process', $event->id));
        $registration = Registration::firstOrFail();
        $registration->paymentAttempts()->firstOrFail()->update(['local_status' => 'failed']);

        $this->post(route('events.register.retry', $event->id), ['protocol' => $registration->protocol])
            ->assertRedirect(route('events.register.status', ['id' => $event->id, 'protocol' => $registration->protocol]));

        $this->assertDatabaseCount('registrations', 1);
        $this->assertDatabaseCount('payment_attempts', 2);
        $this->assertSame('pending', $registration->paymentAttempts()->latest('id')->firstOrFail()->local_status);
    }

    public function test_expired_pix_code_is_hidden_while_provider_status_is_unconfirmed(): void
    {
        $event = Event::factory()->create();
        $this->post(route('events.register.review', $event->id), $this->participant());
        $this->post(route('events.register.process', $event->id));
        $registration = Registration::firstOrFail();
        $this->travel(61)->minutes();

        $this->get(route('events.register.status', ['id' => $event->id, 'protocol' => $registration->protocol]))
            ->assertSee('O prazo deste Pix terminou')
            ->assertDontSee('Copiar código Pix');
    }
}
