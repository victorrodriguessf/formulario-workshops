<?php

namespace App\Services;

use App\Mail\CashRegistrationReserved;
use App\Mail\RegistrationConfirmed;
use App\Models\Registration;
use Illuminate\Support\Facades\Mail;
use Throwable;

class RegistrationMailer
{
    public function sendCashReservation(Registration $registration): void
    {
        if ($registration->payment_method !== 'cash'
            || $registration->status !== 'awaiting_payment'
            || ! $registration->cash_payment_due_at) {
            return;
        }

        $this->sendOnce(
            $registration,
            'cash_reservation_email_sent_at',
            fn (Registration $current) => new CashRegistrationReserved($current),
        );
    }

    public function sendConfirmation(Registration $registration): void
    {
        if ($registration->status !== 'confirmed' || ! $registration->payment_received) {
            return;
        }

        $this->sendOnce(
            $registration,
            'confirmation_email_sent_at',
            fn (Registration $current) => new RegistrationConfirmed($current),
        );
    }

    /**
     * @param  callable(Registration): (CashRegistrationReserved|RegistrationConfirmed)  $mailable
     */
    private function sendOnce(Registration $registration, string $sentAtColumn, callable $mailable): void
    {
        $claimed = Registration::whereKey($registration->id)
            ->whereNull($sentAtColumn)
            ->update([$sentAtColumn => now()]);

        if ($claimed !== 1) {
            return;
        }

        try {
            $current = Registration::with('event')->findOrFail($registration->id);
            Mail::to($current->participant_email)->send($mailable($current));
        } catch (Throwable $exception) {
            Registration::whereKey($registration->id)->update([$sentAtColumn => null]);
            report($exception);
        }
    }
}
