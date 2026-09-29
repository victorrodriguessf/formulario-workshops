<?php

namespace App\Console\Commands;

use App\Models\PaymentAttempt;
use App\Models\Registration;
use App\Models\WebhookInbox;
use App\Services\Payment\PaymentGatewayInterface;
use App\Services\RegistrationMailer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessWebhookInbox extends Command
{
    protected $signature = 'webhook:process';

    protected $description = 'Confere pagamentos notificados e cobranças pendentes no Mercado Pago';

    public function handle(PaymentGatewayInterface $gateway, RegistrationMailer $registrationMailer): int
    {
        $inboxes = WebhookInbox::where('status', 'pending')->oldest()->limit(50)->get();

        foreach ($inboxes as $inbox) {
            $paymentId = $inbox->payload['data']['id'] ?? null;
            $attempt = $paymentId ? PaymentAttempt::where('order_id', $paymentId)->first() : null;
            if (! $attempt) {
                $inbox->update(['status' => 'ignored', 'error_log' => 'Pagamento não encontrado.']);

                continue;
            }

            try {
                $this->reconcile($attempt, $gateway, $registrationMailer);
                $inbox->update(['status' => 'processed', 'error_log' => null]);
            } catch (\Throwable $exception) {
                report($exception);
                $inbox->update(['status' => 'error', 'error_log' => 'Falha temporária na consulta ao provedor.']);
            }
        }

        $pending = PaymentAttempt::whereIn('local_status', ['pending', 'unknown'])
            ->whereNotNull('order_id')->oldest()->limit(50)->get();
        foreach ($pending as $attempt) {
            try {
                $this->reconcile($attempt, $gateway, $registrationMailer);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        $this->retryRegistrationEmails($registrationMailer);

        $this->info("{$inboxes->count()} notificações e {$pending->count()} cobranças pendentes analisadas.");

        return self::SUCCESS;
    }

    private function reconcile(
        PaymentAttempt $attempt,
        PaymentGatewayInterface $gateway,
        RegistrationMailer $registrationMailer,
    ): void {
        $status = $gateway->checkPaymentStatus($attempt)['status'];
        if (! in_array($status, ['paid', 'failed', 'refunded', 'review_required'], true)) {
            return;
        }

        $registration = DB::transaction(function () use ($attempt, $status): Registration {
            $current = PaymentAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $registration = $current->registration()->lockForUpdate()->firstOrFail();

            if ($status === 'paid') {
                $current->update(['local_status' => 'paid']);
                $registration->update(['status' => 'confirmed', 'payment_received' => true]);
            } elseif ($status === 'failed' && $current->local_status !== 'paid') {
                $current->update(['local_status' => 'failed']);
            } elseif ($status === 'refunded') {
                $current->update(['local_status' => 'refunded']);
                $registration->update(['status' => 'cancelled', 'payment_received' => false]);
            } elseif ($status === 'review_required') {
                $current->update(['local_status' => 'disputed']);
                $registration->update(['status' => 'review_required']);
            }

            return $registration;
        });

        if ($status === 'paid') {
            $registrationMailer->sendConfirmation($registration->refresh());
        }
    }

    private function retryRegistrationEmails(RegistrationMailer $registrationMailer): void
    {
        Registration::with('event')
            ->where('status', 'confirmed')
            ->where('payment_received', true)
            ->whereNull('confirmation_email_sent_at')
            ->oldest()
            ->limit(50)
            ->get()
            ->each(fn (Registration $registration) => $registrationMailer->sendConfirmation($registration));

        Registration::with('event')
            ->where('payment_method', 'cash')
            ->where('status', 'awaiting_payment')
            ->where('cash_payment_due_at', '>', now())
            ->whereNull('cash_reservation_email_sent_at')
            ->oldest()
            ->limit(50)
            ->get()
            ->each(fn (Registration $registration) => $registrationMailer->sendCashReservation($registration));
    }
}
