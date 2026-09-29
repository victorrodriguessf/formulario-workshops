<?php

namespace App\Services\Payment;

use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MercadoPagoGateway implements PaymentGatewayInterface
{
    public function createPixPayment(PaymentAttempt $attempt): array
    {
        $token = $this->accessToken();

        $amount = number_format($attempt->amount_cents / 100, 2, '.', '');
        $payer = [
            'email' => $this->payerEmail($attempt),
        ];

        if ($this->isSandbox()) {
            $payer['first_name'] = 'APRO';
        }

        $url = 'https://api.mercadopago.com/v1/orders';
        $payload = [
            'type' => 'online',
            'processing_mode' => 'automatic',
            'total_amount' => $amount,
            'external_reference' => $attempt->registration->protocol,
            'payer' => $payer,
            'transactions' => [
                'payments' => [[
                    'amount' => $amount,
                    'payment_method' => [
                        'id' => 'pix',
                        'type' => 'bank_transfer',
                    ],
                    'expiration_time' => $this->expirationTime($attempt),
                ]],
            ],
        ];

        $response = Http::acceptJson()->connectTimeout(5)->timeout(15)->withToken($token)
            ->withHeaders([
                'X-Idempotency-Key' => $attempt->idempotency_key,
            ])
            ->post($url, $payload);

        if ($response->failed()) {
            Log::error('Mercado Pago rejeitou a geração do Pix.', [
                'status' => $response->status(),
                'error' => is_string($response->json('error')) ? $response->json('error') : 'provider_error',
            ]);

            throw new PaymentRequestRejected('O Mercado Pago recusou os dados da cobrança.');
        }

        $data = $response->json();
        $paymentMethod = $data['transactions']['payments'][0]['payment_method'] ?? [];
        if (empty($data['id']) || empty($paymentMethod['qr_code'])) {
            throw new \RuntimeException('O provedor não retornou um código Pix válido.');
        }

        return [
            'qr_code' => $paymentMethod['qr_code'],
            'qr_code_base64' => $paymentMethod['qr_code_base64'] ?? '',
            'provider_order_id' => (string) $data['id'],
        ];
    }

    public function checkPaymentStatus(PaymentAttempt $attempt): array
    {
        if (! $attempt->order_id) {
            return ['status' => 'unknown'];
        }

        $token = $this->accessToken();
        $url = 'https://api.mercadopago.com/v1/orders/'.$attempt->order_id;

        $response = Http::acceptJson()->connectTimeout(5)->timeout(10)->withToken($token)->get($url);

        if ($response->failed()) {
            return ['status' => 'unknown'];
        }

        $orderStatus = $response->json('status');
        $amount = (int) round(((float) $response->json('total_amount')) * 100);
        if ($amount !== $attempt->amount_cents || $response->json('external_reference') !== $attempt->registration->protocol) {
            return ['status' => 'unknown'];
        }

        if ($orderStatus === 'processed') {
            return ['status' => 'paid'];
        } elseif ($orderStatus === 'refunded') {
            return ['status' => 'refunded'];
        } elseif ($orderStatus === 'charged_back') {
            return ['status' => 'review_required'];
        } elseif (in_array($orderStatus, ['canceled', 'expired', 'failed'], true)) {
            return ['status' => 'failed'];
        }

        return ['status' => 'pending'];
    }

    private function payerEmail(PaymentAttempt $attempt): string
    {
        if (! $this->isSandbox()) {
            return $attempt->registration->participant_email;
        }

        return config('services.payment.test_payer_email') ?: 'test_user_br@testuser.com';
    }

    private function isSandbox(): bool
    {
        return config('services.payment.environment') === 'sandbox';
    }

    private function accessToken(): string
    {
        $token = config('services.payment.access_token');
        if (! is_string($token) || $token === '') {
            throw new \RuntimeException('Credencial do Mercado Pago não configurada.');
        }

        $environment = config('services.payment.environment');
        if (! in_array($environment, ['sandbox', 'production'], true)) {
            throw new \RuntimeException('Ambiente do Mercado Pago inválido.');
        }

        if (app()->isProduction() && $environment !== 'production') {
            throw new \RuntimeException('Credenciais de teste do Mercado Pago não podem ser usadas em produção.');
        }

        if (! app()->isProduction() && $environment === 'production') {
            throw new \RuntimeException('Credenciais de produção do Mercado Pago não podem ser usadas fora de produção.');
        }

        if ($environment === 'production' && str_starts_with($token, 'TEST-')) {
            throw new \RuntimeException('O Access Token de teste não pode ser usado como credencial de produção.');
        }

        return $token;
    }

    private function expirationTime(PaymentAttempt $attempt): string
    {
        $minutes = (int) ceil(now()->diffInSeconds($attempt->expires_at, false) / 60);
        $minutes = max(30, min(43200, $minutes));

        return "PT{$minutes}M";
    }
}
