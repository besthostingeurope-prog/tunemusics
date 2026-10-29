<?php

namespace App\Notifications;

use App\Models\ChatMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NewChatMessage extends Notification
{
    use Queueable;

    public function __construct(public ChatMessage $message)
    {
    }

    public function via($notifiable): array
    {
        // Check if conversation is muted for this user
        $conversation = $this->message->conversation;
        $isMuted =
            $conversation->user_one_id === $notifiable->id
                ? $conversation->user_one_muted_at !== null
                : $conversation->user_two_muted_at !== null;

        if ($isMuted) {
            return ['database']; // Only database, no broadcast
        }

        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable): array
    {
        $sender = $this->message->user;
        $displayName = $this->getSenderDisplayName($sender);

        return [
            'message_id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_name' => $displayName,
            'sender_id' => $sender?->id,
            'sender_image' => $sender?->image,
            'body_preview' => Str::limit($this->message->body, 50),
            'image' => $sender?->image,
            'lines' => [
                [
                    'content' => __(
                        ':username sent you a message:',
                        ['username' => $displayName],
                    ),
                    'type' => 'secondary',
                ],
                [
                    'content' => '"' . Str::limit($this->message->body, 100) . '"',
                    'type' => 'primary',
                ],
            ],
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'message' => $this->message->load('user:id,name,username,email,image', 'attachments'),
        ]);
    }

    private function getSenderDisplayName($sender): string
    {
        if (!$sender) {
            return __('Unknown user');
        }
        return $sender->name
            ?? $sender->username
            ?? ($sender->email ? explode('@', $sender->email)[0] : null)
            ?? __('Unknown user');
    }
}
