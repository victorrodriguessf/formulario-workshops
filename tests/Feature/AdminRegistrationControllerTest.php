<?php

namespace Tests\Feature;

use App\Mail\RegistrationConfirmed;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminRegistrationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_filters_participants_by_course_and_status(): void
    {
        $firstEvent = Event::factory()->create(['title' => 'Bordado']);
        $secondEvent = Event::factory()->create(['title' => 'Crochê']);
        Registration::factory()->create(['event_id' => $firstEvent->id, 'participant_name' => 'Ana Costa', 'status' => 'confirmed']);
        Registration::factory()->create(['event_id' => $secondEvent->id, 'participant_name' => 'Beatriz Lima', 'status' => 'awaiting_payment']);
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.registrations.index', ['event_id' => $firstEvent->id, 'status' => 'confirmed']))
            ->assertSee('Ana Costa')
            ->assertDontSee('Beatriz Lima');
    }

    public function test_admin_can_open_participant_details(): void
    {
        $registration = Registration::factory()->create(['participant_name' => 'Ana Costa']);
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.registrations.show', $registration))
            ->assertSee('Ana Costa')
            ->assertSee($registration->protocol)
            ->assertSee('Histórico de pagamentos');
    }

    public function test_admin_can_confirm_and_reopen_a_cash_payment(): void
    {
        Mail::fake();
        $registration = Registration::factory()->create(['payment_method' => 'cash']);
        $this->actingAs(User::factory()->create());

        $this->patch(route('admin.registrations.payment.update', $registration), ['payment_received' => '1'])
            ->assertRedirect();
        $this->assertDatabaseHas('registrations', [
            'id' => $registration->id,
            'payment_received' => true,
            'status' => 'confirmed',
        ]);
        Mail::assertSent(RegistrationConfirmed::class, 1);

        $this->patch(route('admin.registrations.payment.update', $registration), ['payment_received' => '0'])
            ->assertRedirect();
        $this->assertDatabaseHas('registrations', [
            'id' => $registration->id,
            'payment_received' => false,
            'status' => 'awaiting_payment',
        ]);
    }

    public function test_admin_cannot_manually_change_a_pix_payment(): void
    {
        $registration = Registration::factory()->create(['payment_method' => 'pix']);
        $this->actingAs(User::factory()->create());

        $this->from(route('admin.registrations.show', $registration))
            ->patch(route('admin.registrations.payment.update', $registration), ['payment_received' => '1'])
            ->assertRedirect(route('admin.registrations.show', $registration))
            ->assertSessionHasErrors('payment_received');

        $this->assertDatabaseHas('registrations', [
            'id' => $registration->id,
            'payment_received' => false,
            'status' => 'awaiting_payment',
        ]);
    }

    public function test_admin_cannot_confirm_an_expired_cash_reservation(): void
    {
        $registration = Registration::factory()->create([
            'payment_method' => 'cash',
            'status' => 'expired',
            'cash_payment_due_at' => now()->subMinute(),
        ]);
        $this->actingAs(User::factory()->create());

        $this->patch(route('admin.registrations.payment.update', $registration), ['payment_received' => '1'])
            ->assertSessionHasErrors('payment_received');

        $this->assertDatabaseHas('registrations', [
            'id' => $registration->id,
            'status' => 'expired',
            'payment_received' => false,
        ]);
    }

    public function test_guest_cannot_change_cash_payment(): void
    {
        $registration = Registration::factory()->create(['payment_method' => 'cash']);

        $this->patch(route('admin.registrations.payment.update', $registration), ['payment_received' => '1'])
            ->assertRedirect(route('admin.login'));

        $this->assertFalse($registration->refresh()->payment_received);
    }
}
