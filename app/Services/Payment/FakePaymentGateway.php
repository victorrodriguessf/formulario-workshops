<?php

namespace App\Services\Payment;

use App\Models\PaymentAttempt;
use Illuminate\Support\Str;

class FakePaymentGateway implements PaymentGatewayInterface
{
    public function createPixPayment(PaymentAttempt $attempt): array
    {
        return [
            'qr_code' => '00020101021126580014br.gov.bcb.pix0136FAKE-PIX-CODE-'.strtoupper(Str::random(10)),
            'qr_code_base64' => '', // Fica vazio para o mock visual sem imagem
            'provider_order_id' => 'fake_order_'.Str::random(10),
        ];
    }

    public function checkPaymentStatus(PaymentAttempt $attempt): array
    {
        // No fake, se a data do status da requisição for 'success', simularemos pago, senão fica pendente.
        // O controle da simulação é feito primariamente no controller, mas o Fake responde pending.
        return [
            'status' => 'pending',
        ];
    }
}
