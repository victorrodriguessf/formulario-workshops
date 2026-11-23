<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'title',
    'slug',
    'description',
    'place',
    'materials',
    'banner_path',
    'banner_alt',
    'price_cents',
    'starts_at',
    'ends_at',
    'registrations_open_at',
    'registrations_close_at',
    'cash_payment_deadline',
    'status',
    'is_paused',
    'capacity',
])]
class Event extends Model
{
    use HasFactory, HasUlids;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paidRegistrations(): HasMany
    {
        return $this->registrations()->where('payment_received', true);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function occupiedRegistrations(): HasMany
    {
        return $this->registrations()->whereNotIn('status', ['cancelled', 'expired']);
    }

    public function hasAvailableSpots(): bool
    {
        if ($this->capacity === null) {
            return true;
        }

        $occupiedSpots = $this->getAttribute('occupied_registrations_count');

        return ($occupiedSpots ?? $this->occupiedRegistrations()->count()) < $this->capacity;
    }

    public function registrationPeriodIsOpen(): bool
    {
        return $this->price_cents > 0
            && ! $this->is_paused
            && (! $this->registrations_open_at || now()->greaterThanOrEqualTo($this->registrations_open_at))
            && (! $this->registrations_close_at || now()->addMinutes(30)->lessThanOrEqualTo($this->registrations_close_at));
    }

    public function acceptsNewRegistrations(): bool
    {
        return $this->registrationPeriodIsOpen() && $this->hasAvailableSpots();
    }

    public function cashPaymentIsAvailable(): bool
    {
        return $this->cash_payment_deadline?->isFuture() ?? false;
    }

    public function getBannerUrlAttribute(): ?string
    {
        if (! $this->banner_path) {
            return null;
        }

        if (Str::startsWith($this->banner_path, ['http://', 'https://'])) {
            return $this->banner_path;
        }

        return asset('storage/'.$this->banner_path);
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'registrations_open_at' => 'datetime',
            'registrations_close_at' => 'datetime',
            'cash_payment_deadline' => 'datetime',
            'is_paused' => 'boolean',
            'capacity' => 'integer',
        ];
    }
}
