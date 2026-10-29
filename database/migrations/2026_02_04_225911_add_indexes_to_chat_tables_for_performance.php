<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add indexes to chat_conversations for faster queries
        Schema::table('chat_conversations', function (Blueprint $table) {
            // Index for broadcast channel authentication
            $table->index(['user_one_id', 'user_two_id'], 'idx_conversation_users');

            // Index for conversation list queries (ordered by last message)
            $table->index(['user_one_id', 'user_one_archived_at', 'last_message_at'], 'idx_user_one_active');
            $table->index(['user_two_id', 'user_two_archived_at', 'last_message_at'], 'idx_user_two_active');
        });

        // Add indexes to chat_messages for faster queries
        Schema::table('chat_messages', function (Blueprint $table) {
            // Index for fetching messages in a conversation
            $table->index(['conversation_id', 'deleted_at', 'created_at'], 'idx_conversation_messages');

            // Index for unread count calculations
            $table->index(['conversation_id', 'user_id', 'created_at'], 'idx_conversation_user_messages');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropIndex('idx_conversation_users');
            $table->dropIndex('idx_user_one_active');
            $table->dropIndex('idx_user_two_active');
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex('idx_conversation_messages');
            $table->dropIndex('idx_conversation_user_messages');
        });
    }
};
