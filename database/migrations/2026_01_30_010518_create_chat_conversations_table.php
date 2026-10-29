<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();

            // Participants (user_one_id always < user_two_id)
            $table->unsignedInteger('user_one_id');
            $table->unsignedInteger('user_two_id');

            // Last message (denormalized for performance)
            $table->unsignedBigInteger('last_message_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->string('last_message_body', 200)->nullable();
            $table->unsignedInteger('last_message_user_id')->nullable();
            $table->smallInteger('last_message_attachments_count')->default(0);

            // Foreign keys
            $table->foreign('user_one_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('user_two_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('last_message_user_id')->references('id')->on('users')->onDelete('set null');

            // User one state
            $table->timestamp('user_one_read_at')->nullable();
            $table->bigInteger('user_one_last_read_message_id')->nullable();
            $table->integer('user_one_unread_count')->default(0);
            $table->timestamp('user_one_archived_at')->nullable();
            $table->timestamp('user_one_muted_at')->nullable();

            // User two state
            $table->timestamp('user_two_read_at')->nullable();
            $table->bigInteger('user_two_last_read_message_id')->nullable();
            $table->integer('user_two_unread_count')->default(0);
            $table->timestamp('user_two_archived_at')->nullable();
            $table->timestamp('user_two_muted_at')->nullable();

            // Moderation
            $table->timestamp('blocked_at')->nullable();
            $table->unsignedInteger('blocked_by')->nullable();
            $table->text('blocked_reason')->nullable();

            $table->foreign('blocked_by')->references('id')->on('users')->onDelete('set null');

            $table->timestamps();

            // Indexes
            $table->unique(['user_one_id', 'user_two_id']);
            $table->index(['user_one_id', 'user_one_archived_at', 'last_message_at'], 'conv_user_one_idx');
            $table->index(['user_two_id', 'user_two_archived_at', 'last_message_at'], 'conv_user_two_idx');
            $table->index('last_message_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};
