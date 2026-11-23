<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

/**
 * Cada organizador só gerencia as oficinas que criou.
 */
class EventPolicy
{
    public function view(User $user, Event $event): bool
    {
        return $this->owns($user, $event);
    }

    public function update(User $user, Event $event): bool
    {
        return $this->owns($user, $event);
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->owns($user, $event);
    }

    private function owns(User $user, Event $event): bool
    {
        return $event->user_id !== null && $event->user_id === $user->id;
    }
}
