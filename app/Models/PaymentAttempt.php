<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'registration_id',
    'idempotency_key',
    'environment',
    'order_id',
    'transaction_id',
    'amount_cents',
    'currency',
    'local_status',
    'provider_status',
    'expires_at',
    'provider_created_at',
])]
#[Hidden(['idempotency_key', 'order_id', 'transaction_id'])]
class PaymentAttempt extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
