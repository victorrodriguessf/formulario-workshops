<?php

namespace Tests\Feature;

use App\Models\PaymentAttempt;
use App\Models\Registration;
use App\Services\Payment\MercadoPagoGateway;
use App\Services\Payment\PaymentRequestRejected;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MercadoPagoGatewayTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function attempt(): PaymentAttempt
    {
        $registration = Registration::factory()->create();

        return PaymentAttempt::create([
            'registration_id' => $registration->id,
            'idempotency_key' => 'fixed-idempotency-key',
            'environment' => 'testing',
            'order_id' => '123',
            'amount_cents' => 8590,
            'currency' => 'BRL',
            'local_status' => 'pending',
            'expires_at' => now()->addHour(),
        ]);
    }

    public function test_pix_creation_uses_orders_api_payload_and_expiration(): void
    {
        config()->set('services.payment.access_token', 'test-token');
        $attempt = $this->attempt();
        Http::preventStrayRequests();
        Http::fake(['https://api.mercadopago.com/v1/orders' => Http::response([
            'id' => 'ORD-123',
            'transactions' => ['payments' => [[
                'payment_method' => ['qr_code' => 'pix-code', 'qr_code_base64' => 'image-data'],
            ]]],
        ])]);

        $result = (new MercadoPagoGateway)->createPixPayment($attempt);

        $this->assertSame('pix-code', $result['qr_code']);
        $this->assertSame('ORD-123', $result['provider_order_id']);
        Http::assertSent(fn ($request) => $request->data()['type'] === 'online'
            && $request->data()['processing_mode'] === 'automatic'
            && $request->data()['total_amount'] === '85.90'
            && $request->data()['transactions']['payments'][0]['amount'] === '85.90'
            && $request->data()['transactions']['payments'][0]['payment_method'] === ['id' => 'pix', 'type' => 'bank_transfer']
            && $request->data()['transactions']['payments'][0]['expiration_time'] === 'PT60M'
            && $request->hasHeader('X-Idempotency-Key', 'fixed-idempotency-key'));
    }

    public function test_approved_payment_with_different_amount_is_not_confirmed(): void
    {
        config()->set('services.payment.access_token', 'test-token');
        $attempt = $this->attempt();
        Http::preventStrayRequests();
        Http::fake(['https://api.mercadopago.com/v1/orders/123' => Http::response([
            'status' => 'processed',
            'total_amount' => '10.00',
            'external_reference' => $attempt->registration->protocol,
        ])]);

        $result = (new MercadoPagoGateway)->checkPaymentStatus($attempt);

        $this->assertSame('unknown', $result['status']);
    }

    public function test_processed_order_with_matching_amount_and_reference_is_paid(): void
    {
        config()->set('services.payment.access_token', 'test-token');
        $attempt = $this->attempt();
        Http::preventStrayRequests();
        Http::fake(['https://api.mercadopago.com/v1/orders/123' => Http::response([
            'status' => 'processed',
            'total_amount' => '85.90',
            'external_reference' => $attempt->registration->protocol,
        ])]);

        $result = (new MercadoPagoGateway)->checkPaymentStatus($attempt);

        $this->assertSame('paid', $result['status']);
    }

    public function test_refunded_order_with_a_different_reference_is_not_accepted(): void
    {
        config()->set('services.payment.access_token', 'test-token');
        $attempt = $this->attempt();
        Http::preventStrayRequests();
        Http::fake(['https://api.mercadopago.com/v1/orders/123' => Http::response([
            'status' => 'refunded',
            'total_amount' => '85.90',
            'external_reference' => 'another-registration',
        ])]);

        $result = (new MercadoPagoGateway)->checkPaymentStatus($attempt);

        $this->assertSame('unknown', $result['status']);
    }

    public function test_production_credentials_are_rejected_outside_production(): void
    {
        config()->set('services.payment.access_token', 'APP_USR-production-token');
        config()->set('services.payment.environment', 'production');
        $attempt = $this->attempt();
        Http::preventStrayRequests();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Credenciais de produção do Mercado Pago não podem ser usadas fora de produção.');

        (new MercadoPagoGateway)->createPixPayment($attempt);
    }

    public function test_pix_creation_uses_sandbox_email_with_a_test_token(): void
    {
        config()->set('services.payment.access_token', 'TEST-123456');
        config()->set('services.payment.test_payer_email', 'buyer@testuser.com');
        $attempt = $this->attempt();
        Http::preventStrayRequests();
        Http::fake(['https://api.mercadopago.com/v1/orders' => Http::response([
            'id' => 'ORD-123',
            'transactions' => ['payments' => [[
                'payment_method' => ['qr_code' => 'pix-code'],
            ]]],
        ])]);

        (new MercadoPagoGateway)->createPixPayment($attempt);

        Http::assertSent(fn ($request): bool => $request->data()['payer']['email'] === 'buyer@testuser.com'
            && $request->data()['payer']['first_name'] === 'APRO');
    }

    public function test_rejected_pix_request_throws_a_specific_exception(): void
    {
        config()->set('services.payment.access_token', 'TEST-123456');
        $attempt = $this->attempt();
        Http::preventStrayRequests();
        Http::fake(['https://api.mercadopago.com/v1/orders' => Http::response([
            'error' => 'bad_request',
            'message' => 'Invalid payer email',
        ], 400)]);

        $this->expectException(PaymentRequestRejected::class);

        (new MercadoPagoGateway)->createPixPayment($attempt);
    }
}
