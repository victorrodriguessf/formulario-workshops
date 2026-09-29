<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'event_id',
    'participant_name',
    'participant_whatsapp',
    'participant_email',
    'participant_cpf_encrypted',
    'cpf_lookup_hmac',
    'status',
    'payment_method',
    'payment_received',
    'cash_payment_due_at',
    'cash_reservation_email_sent_at',
    'confirmation_email_sent_at',
    'agreed_price_cents',
    'protocol',
    'privacy_policy_version',
])]
#[Hidden([
    'participant_name',
    'participant_whatsapp',
    'participant_email',
    'participant_cpf_encrypted',
    'cpf_lookup_hmac',
])]
class Registration extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'payment_received' => 'boolean',
            'cash_payment_due_at' => 'datetime',
            'cash_reservation_email_sent_at' => 'datetime',
            'confirmation_email_sent_at' => 'datetime',
        ];
    }

    public function cashPaymentIsOverdue(): bool
    {
        return $this->payment_method === 'cash'
            && $this->status === 'awaiting_payment'
            && $this->cash_payment_due_at?->isPast();
    }

    public function expireOverdueCashPayment(): bool
    {
        if (! $this->cashPaymentIsOverdue()) {
            return false;
        }

        return $this->update(['status' => 'expired']);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }
}
