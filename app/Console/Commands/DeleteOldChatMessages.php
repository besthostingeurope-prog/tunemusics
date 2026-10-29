<?php

namespace App\Console\Commands;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Common\Settings\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class DeleteOldChatMessages extends Command
{
    protected $signature = 'chat:cleanup';

    protected $description = 'Delete chat messages older than retention period';

    public function handle(Settings $settings): int
    {
        $months = $settings->get('chat.retention_months', 12);
        $date = now()->subMonths($months);

        $count = 0;
        $affectedConversationIds = [];

        $this->info(
            "Deleting messages older than {$months} months (before {$date->toDateString()})...",
        );

        // Use chunking to avoid memory spikes
        ChatMessage::where('created_at', '<', $date)->chunkById(
            100,
            function ($messages) use (&$count, &$affectedConversationIds) {
                foreach ($messages as $message) {
                    $affectedConversationIds[] = $message->conversation_id;

                    // Delete attachments first
                    $message
                        ->attachments()
                        ->each(function ($attachment) {
                            $attachment->delete(); // Handles file cleanup via file system
                        });

                    // Force delete message
                    $message->forceDelete();
                    $count++;
                }
            },
        );

        $this->info("Deleted {$count} messages");

        if (!empty($affectedConversationIds)) {
            // Recalculate unread counts + last message for affected conversations
            $affectedConversationIds = array_values(
                array_unique($affectedConversationIds),
            );
            $conversationCount = count($affectedConversationIds);
            $this->info(
                "Updating {$conversationCount} affected conversations...",
            );

            ChatConversation::whereIn('id', $affectedConversationIds)->chunk(
                100,
                function ($conversations) {
                    foreach ($conversations as $conversation) {
                        // Verify last message still exists
                        if (
                            !$conversation
                                ->lastMessage()
                                ->exists()
                        ) {
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
                                    'last_message_at' =>
                                        $newLastMessage->created_at,
                                    'last_message_body' => Str::limit(
                                        $newLastMessage->body,
                                        200,
                                    ),
                                    'last_message_user_id' =>
                                        $newLastMessage->user_id,
                                    'last_message_attachments_count' =>
                                        $attachmentsCount,
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
                            $conversation->recalculateUnreadCounts();
                        }
                    }
                },
            );
        }

        $this->info('Cleanup completed successfully');

        return Command::SUCCESS;
    }
}
