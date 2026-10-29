<?php

namespace App\Notifications;

use App\Models\BackstageRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewBackstageRequestReceived extends Notification
{
    use Queueable;

    public function __construct(protected BackstageRequest $backstageRequest)
    {
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    protected function typeLabel(): string
    {
        return match ($this->backstageRequest->type) {
            'claim-artist' => __('claim an artist profile'),
            'become-artist' => __('become an artist'),
            'verify-artist' => __('verify an artist'),
            default => $this->backstageRequest->type,
        };
    }

    protected function summary(): string
    {
        return __(':user has requested to :type (:artist)', [
            'user' =>
                $this->backstageRequest->user->name ??
                $this->backstageRequest->user->email,
            'type' => $this->typeLabel(),
            'artist' =>
                $this->backstageRequest->artist->name ??
                ($this->backstageRequest->artist_name ?? '-'),
        ]);
    }

    protected function adminUrl(): string
    {
        return url('admin/backstage-requests/' . $this->backstageRequest->id);
    }

    public function toMail($notifiable)
    {
        return (new MailMessage())
            ->subject(
                __(':siteName - new backstage request', [
                    'siteName' => config('app.name'),
                ]),
            )
            ->line($this->summary())
            ->action(__('Review request'), $this->adminUrl());
    }

    public function toArray(): array
    {
        return [
            'mainAction' => [
                'action' => $this->adminUrl(),
                'label' => __('Review request'),
            ],
            'lines' => [
                [
                    'content' => $this->summary(),
                    'type' => 'primary',
                ],
            ],
        ];
    }
}
