<?php

namespace Tests\Feature;

use App\Mail\CashRegistrationReserved;
use App\Mail\RegistrationConfirmed;
use App\Models\PaymentAttempt;
use App\Models\Registration;
use App\Services\Payment\PaymentGatewayInterface;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProcessWebhookInboxTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_webhook_reconciles_payment_with_provider_before_confirming(): void
    {
        Mail::fake();
        $registration = Registration::factory()->create();
        $attempt = PaymentAttempt::create([
            'registration_id' => $registration->id,
            'idempotency_key' => 'webhook-test-key',
            'environment' => 'testing',
            'order_id' => 'mp_123',
            'amount_cents' => 8500,
            'currency' => 'BRL',
            'local_status' => 'pending',
        ]);
        $this->mock(PaymentGatewayInterface::class)->shouldReceive('checkPaymentStatus')->once()->andReturn(['status' => 'paid']);

        $this->postJson(route('webhook.mercadopago'), ['data' => ['id' => 'mp_123']])->assertOk();
        $this->artisan('webhook:process')->assertSuccessful();

        $this->assertDatabaseHas('payment_attempts', ['id' => $attempt->id, 'local_status' => 'paid']);
        $this->assertDatabaseHas('registrations', ['id' => $registration->id, 'status' => 'confirmed', 'payment_received' => true]);
        $this->assertDatabaseHas('webhook_inboxes', ['status' => 'processed']);
        Mail::assertSent(RegistrationConfirmed::class, 1);
    }

    public function test_webhook_rejects_invalid_signature_when_secret_is_configured(): void
    {
        config()->set('services.payment.webhook_secret', 'test-secret');

        $this->withHeaders(['x-request-id' => 'request-1', 'x-signature' => 'ts=1700000000,v1='.str_repeat('0', 64)])
            ->postJson(route('webhook.mercadopago'), ['data' => ['id' => 'mp_123']])
            ->assertUnauthorized();

        $this->assertDatabaseCount('webhook_inboxes', 0);
    }

    public function test_webhook_accepts_valid_signature_when_secret_is_configured(): void
    {
        config()->set('services.payment.webhook_secret', 'test-secret');
        $signature = hash_hmac('sha256', 'id:mp_123;request-id:request-1;ts:1700000000;', 'test-secret');

        $this->withHeaders(['x-request-id' => 'request-1', 'x-signature' => 'ts=1700000000,v1='.$signature])
            ->postJson(route('webhook.mercadopago'), ['data' => ['id' => 'mp_123']])
            ->assertOk();

        $this->assertDatabaseHas('webhook_inboxes', ['status' => 'pending']);
    }

    public function test_cron_confirms_pending_payment_even_without_a_webhook(): void
    {
        $registration = Registration::factory()->create();
        PaymentAttempt::create([
            'registration_id' => $registration->id,
            'idempotency_key' => 'polling-test-key',
            'environment' => 'testing',
            'order_id' => 'mp_456',
            'amount_cents' => 8500,
            'currency' => 'BRL',
            'local_status' => 'pending',
        ]);
        $this->mock(PaymentGatewayInterface::class)->shouldReceive('checkPaymentStatus')->once()->andReturn(['status' => 'paid']);

        $this->artisan('webhook:process')->assertSuccessful();

        $this->assertDatabaseHas('registrations', ['id' => $registration->id, 'status' => 'confirmed', 'payment_received' => true]);
    }

    public function test_cron_retries_an_unsent_cash_reservation_email(): void
    {
        Mail::fake();
        $registration = Registration::factory()->create([
            'payment_method' => 'cash',
            'cash_payment_due_at' => now()->addDay(),
            'cash_reservation_email_sent_at' => null,
        ]);

        $this->artisan('webhook:process')->assertSuccessful();

        Mail::assertSent(CashRegistrationReserved::class, 1);
        $this->assertNotNull($registration->refresh()->cash_reservation_email_sent_at);
    }
}
