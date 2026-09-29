<?php

namespace App\Providers;

use App\Services\Payment\FakePaymentGateway;
use App\Services\Payment\MercadoPagoGateway;
use App\Services\Payment\PaymentGatewayInterface;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            $gateway = config('services.payment.gateway');

            if ($gateway === 'fake') {
                if ($app->environment('production')) {
                    throw new \RuntimeException('O gateway de pagamento simulado não pode ser usado em produção.');
                }

                return new FakePaymentGateway;
            }

            if ($gateway === 'mercadopago') {
                return new MercadoPagoGateway;
            }

            throw new \RuntimeException('Gateway de pagamento não reconhecido.');
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
