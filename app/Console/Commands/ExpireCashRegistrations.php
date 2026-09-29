<?php

namespace App\Console\Commands;

use App\Models\Registration;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('registrations:expire-cash')]
#[Description('Expira reservas em dinheiro que não foram pagas até o prazo')]
class ExpireCashRegistrations extends Command
{
    public function handle(): int
    {
        $expired = Registration::where('payment_method', 'cash')
            ->where('status', 'awaiting_payment')
            ->whereNotNull('cash_payment_due_at')
            ->where('cash_payment_due_at', '<', now())
            ->update(['status' => 'expired', 'updated_at' => now()]);

        $this->info("{$expired} reservas em dinheiro expiradas.");

        return self::SUCCESS;
    }
}
