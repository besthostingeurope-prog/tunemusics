<?php

namespace Common\Auth\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class WelcomeEmail extends Notification
{
    public function __construct(public ?string $password = null)
    {
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $loginUrl = url('/login');
        $siteName = config('app.name');
        $pStyle =
            'line-height: 30px; text-align: left; font-weight: normal; font-style: normal; letter-spacing: 0.48px; color: #718096';
        $emailStyle =
            'font-size: 16px; font-weight: 600; padding: 12px 16px; background: #f7fafc; border-radius: 6px; color: #3d4852; display: inline-block; margin: 8px 0';

        $title = __('Welcome to :site!', ['site' => $siteName]);
        $intro = __('Your account has been created successfully.');
        $emailLabel = __('Your login email:');
        $passwordLabel = __('Your password:');
        $loginHint = __('Log in with your email and password to access your account.');
        $securityNote = __('Keep your password secure and do not share it with anyone.');

        $message = (new MailMessage())
            ->subject(__('Welcome to :site', ['site' => $siteName]))
            ->greeting(new HtmlString("<h1 style=\"$pStyle\">$title</h1>"))
            ->line(new HtmlString("<p style=\"$pStyle\">$intro</p>"))
            ->line(new HtmlString("<p style=\"$pStyle\">$emailLabel</p>"))
            ->line(
                new HtmlString(
                    '<span style="' . $emailStyle . '">' . e($notifiable->email) . '</span>',
                ),
            );

        if ($this->password) {
            $message->line(new HtmlString("<p style=\"$pStyle\">$passwordLabel</p>"))
                ->line(
                    new HtmlString(
                        '<span style="' . $emailStyle . '">' . e($this->password) . '</span>',
                    ),
                );
        }

        return $message->line(new HtmlString("<p style=\"$pStyle\">$loginHint</p>"))
            ->action(__('Log in'), $loginUrl)
            ->line(new HtmlString("<p style=\"$pStyle; font-size: 14px; color: #718096\">$securityNote</p>"));
    }
}
