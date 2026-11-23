<?php

namespace App\Policies;

use App\Models\Registration;
use App\Models\User;

/**
 * Uma inscrição pertence ao organizador dono da oficina em que ela foi feita.
 */
class RegistrationPolicy
{
    public function view(User $user, Registration $registration): bool
    {
        return $this->owns($user, $registration);
    }

    public function update(User $user, Registration $registration): bool
    {
        return $this->owns($user, $registration);
    }

    public function delete(User $user, Registration $registration): bool
    {
        return $this->owns($user, $registration);
    }

    private function owns(User $user, Registration $registration): bool
    {
        $ownerId = $registration->event?->user_id;

        return $ownerId !== null && $ownerId === $user->id;
    }
}
