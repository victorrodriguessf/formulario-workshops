<?php

namespace App\Services\Payment;

use App\Models\PaymentAttempt;

interface PaymentGatewayInterface
{
    /**
     * @return array ['qr_code' => string, 'qr_code_base64' => string, 'provider_order_id' => string]
     */
    public function createPixPayment(PaymentAttempt $attempt): array;

    /**
     * @return array ['status' => string]
     */
    public function checkPaymentStatus(PaymentAttempt $attempt): array;
}
