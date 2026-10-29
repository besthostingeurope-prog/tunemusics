<?php

namespace App\Observers;

use App\Events\ChatListNeedsRefresh;
use App\Events\ChatMessageCreated;
use App\Events\ChatMessageDeleted;
use App\Events\ChatMessageUpdated;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Notifications\NewChatMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatMessageObserver
{
    /**
     * Handle the ChatMessage "created" event.
     */
    public function created(ChatMessage $message): void
    {
        $conversation = $message->conversation;
        $sender = $message->user;

        // Update last message on conversation
        $conversation->update([
            'last_message_id' => $message->id,
            'last_message_at' => $message->created_at,
            'last_message_body' => Str::limit($message->body, 200),
            'last_message_user_id' => $sender->id,
            // Attachments might be linked later in the same DB transaction.
            // We reconcile the exact count in the after-commit callback.
            'last_message_attachments_count' => 0,
        ]);

        // Increment unread for recipient
        $recipient = $conversation->getOtherUser($sender);
        $conversation->incrementUnreadFor($recipient);

        // Notify + broadcast (after commit to avoid ghost events)
        $afterCommit = function () use ($recipient, $message, $sender, $conversation) {
            $freshMessage = ChatMessage::query()
                ->with(['user:id,name,username,email,image', 'attachments'])
                ->find($message->id);

            if (!$freshMessage) {
                return;
            }

            $attachmentsCount = $freshMessage->attachments->count();

            // Ensure denormalized conversation preview metadata is consistent
            // with the fully persisted message record (body + attachments).
            ChatConversation::query()
                ->where('id', $conversation->id)
                ->where('last_message_id', $freshMessage->id)
                ->update([
                    'last_message_at' => $freshMessage->created_at,
                    'last_message_body' => Str::limit($freshMessage->body, 200),
                    'last_message_user_id' => $freshMessage->user_id,
                    'last_message_attachments_count' => $attachmentsCount,
                    'updated_at' => now(),
                ]);

            try {
                // Send notification to recipient
                $recipient->notify(new NewChatMessage($freshMessage));
            } catch (\Exception $e) {
                \Log::error('Failed to send chat notification', [
                    'message_id' => $freshMessage->id,
                    'recipient_id' => $recipient->id,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                // Broadcast to both users (not using toOthers to ensure delivery)
                if (config('app.debug')) {
                    \Log::debug('Broadcasting ChatMessageCreated', [
                        'message_id' => $freshMessage->id,
                        'conversation_id' => $freshMessage->conversation_id,
                        'sender_id' => $sender->id,
                        'recipient_id' => $recipient->id,
                        'broadcast_driver' => config('broadcasting.default'),
                    ]);
                }

                broadcast(new ChatMessageCreated($freshMessage));
                broadcast(new ChatListNeedsRefresh($freshMessage->conversation_id, $recipient->id, $sender->id));
                broadcast(new ChatListNeedsRefresh($freshMessage->conversation_id, $sender->id, $sender->id));
            } catch (\Exception $e) {
                \Log::error('Failed to broadcast ChatMessageCreated', [
                    'message_id' => $freshMessage->id,
                    'conversation_id' => $freshMessage->conversation_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($afterCommit);
        } else {
            $afterCommit();
        }
    }

    /**
     * Handle the ChatMessage "updated" event.
     */
    public function updated(ChatMessage $message): void
    {
        $conversation = $message->conversation;

        // If this is the last message, update preview
        if ($conversation->last_message_id === $message->id) {
            $attachmentsCount = $message->relationLoaded('attachments')
                ? $message->attachments->count()
                : $message->attachments()->count();

            $conversation->update([
                'last_message_body' => Str::limit($message->body, 200),
                'last_message_attachments_count' => $attachmentsCount,
            ]);
        }

        try {
            broadcast(new ChatMessageUpdated($message));
            broadcast(new ChatListNeedsRefresh($conversation->id, $conversation->user_one_id));
            broadcast(new ChatListNeedsRefresh($conversation->id, $conversation->user_two_id));
            if (config('app.debug')) {
                \Log::debug('Broadcasting ChatMessageUpdated', [
                    'message_id' => $message->id,
                    'conversation_id' => $message->conversation_id,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to broadcast ChatMessageUpdated', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the ChatMessage "deleted" event.
     */
    public function deleted(ChatMessage $message): void
    {
        $conversation = $message->conversation;

        // If deleted message was the last message, find new last message
        if ($conversation->last_message_id === $message->id) {
            $newLastMessage = $conversation
                ->messages()
                ->whereNull('deleted_at')
                ->latest()
                ->first();

            if ($newLastMessage) {
                $attachmentsCount = $newLastMessage->relationLoaded(
                    'attachments',
                )
                    ? $newLastMessage->attachments->count()
                    : $newLastMessage->attachments()->count();

                $conversation->update([
                    'last_message_id' => $newLastMessage->id,
                    'last_message_at' => $newLastMessage->created_at,
                    'last_message_body' => Str::limit(
                        $newLastMessage->body,
                        200,
                    ),
                    'last_message_user_id' => $newLastMessage->user_id,
                    'last_message_attachments_count' => $attachmentsCount,
                ]);
            } else {
                $conversation->update([
                    'last_message_id' => null,
                    'last_message_at' => null,
                    'last_message_body' => null,
                    'last_message_user_id' => null,
                    'last_message_attachments_count' => 0,
                ]);
            }

            // Recalculate unread counts since message was deleted
            $conversation->recalculateUnreadCounts();
        }

        try {
            broadcast(new ChatMessageDeleted($message));
            broadcast(new ChatListNeedsRefresh($conversation->id, $conversation->user_one_id));
            broadcast(new ChatListNeedsRefresh($conversation->id, $conversation->user_two_id));
            if (config('app.debug')) {
                \Log::debug('Broadcasting ChatMessageDeleted', [
                    'message_id' => $message->id,
                    'conversation_id' => $message->conversation_id,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to broadcast ChatMessageDeleted', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
