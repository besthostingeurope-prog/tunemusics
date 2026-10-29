<?php

namespace Common\Auth\Listeners;

use Common\Auth\Events\UserCreated;
use Common\Auth\Notifications\WelcomeEmail;

class SendWelcomeEmail
{
    public function handle(UserCreated $event): void
    {
        $user = $event->user;
        if ($user->email) {
            $user->notify(new WelcomeEmail());
        }
    }
}
