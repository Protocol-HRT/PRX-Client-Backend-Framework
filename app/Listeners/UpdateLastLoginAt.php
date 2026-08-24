<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Stamps users.last_login_at on successful auth. Auto-discovered by Laravel
 * 11+ event discovery — no manual EventServiceProvider mapping needed.
 */
class UpdateLastLoginAt
{
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        // Use saveQuietly so we don't fire model observers or touch
        // updated_at on every login.
        $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
    }
}
