<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChatMessage $message)
    {
    }

    public function broadcastOn(): Channel
    {
        return new PrivateChannel(
            'App.Conversation.' . $this->message->conversation_id,
        );
    }

    public function broadcastAs(): string
    {
        return 'ChatMessageUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message->load('user:id,name,image', 'attachments'),
        ];
    }
}
