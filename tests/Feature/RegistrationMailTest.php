<?php

namespace Tests\Feature;

use App\Mail\CashRegistrationReserved;
use App\Mail\RegistrationConfirmed;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RegistrationMailTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cash_reservation_email_contains_payment_deadline_and_protocol(): void
    {
        $event = Event::factory()->create(['title' => 'Oficina de Bordado']);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'payment_method' => 'cash',
            'cash_payment_due_at' => '2026-10-10 18:30:00',
            'protocol' => 'APP-RESERVA01',
        ]);

        $message = (new CashRegistrationReserved($registration->load('event')))->render();

        $this->assertStringContainsString('Sua vaga foi reservada', $message);
        $this->assertStringContainsString('10/10/2026 às 18:30', $message);
        $this->assertStringContainsString('APP-RESERVA01', $message);
    }

    public function test_confirmation_email_contains_course_and_protocol(): void
    {
        $event = Event::factory()->create(['title' => 'Oficina de Crochê']);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => 'confirmed',
            'payment_received' => true,
            'protocol' => 'APP-CONFIRMA1',
        ]);

        $message = (new RegistrationConfirmed($registration->load('event')))->render();

        $this->assertStringContainsString('Inscrição confirmada', $message);
        $this->assertStringContainsString('Oficina de Crochê', $message);
        $this->assertStringContainsString('APP-CONFIRMA1', $message);
    }
}
