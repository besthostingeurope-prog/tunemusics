<?php

namespace App\Listeners;

use Common\Auth\Events\UserCreated;
use Common\Auth\Notifications\WelcomeEmail;

class SendWelcomeEmail
{
    public function handle(UserCreated $event): void
    {
        $user = $event->user;
        if ($user->email) {
            $password = $event->data['password'] ?? null;
            $user->notify(new WelcomeEmail($password));
        }
    }
}
