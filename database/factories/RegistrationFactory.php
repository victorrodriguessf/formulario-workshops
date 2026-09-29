<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Registration;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * @extends Factory<Registration>
 */
class RegistrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'participant_name' => fake()->name(),
            'participant_whatsapp' => '84999999999',
            'participant_email' => fake()->safeEmail(),
            'participant_cpf_encrypted' => Crypt::encryptString('52998224725'),
            'cpf_lookup_hmac' => hash_hmac('sha256', fake()->unique()->numerify('###########'), config('app.key')),
            'status' => 'awaiting_payment',
            'payment_method' => 'pix',
            'payment_received' => false,
            'agreed_price_cents' => 8500,
            'protocol' => 'APP-'.strtoupper(Str::random(10)),
        ];
    }
}
