<?php

namespace App\Policies;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Common\Core\Policies\BasePolicy;
use Common\Settings\Settings;

class ChatPolicy extends BasePolicy
{
    public function __construct(protected Settings $settings)
    {
    }

    public function index(User $user)
    {
        $hasPermission = $this->hasPermission($user, 'chat.view');

        return $hasPermission ? $this->allow() : $this->deny(__('You do not have permission to view chat conversations.'));
    }

    public function store(User $user, User $recipient)
    {
        if (!$this->hasPermission($user, 'chat.send')) {
            return $this->deny(__('Support your artist! Upgrade to Pro to message artists directly'));
        }

        // Prevent self-chat
        if ($user->id === $recipient->id) {
            return $this->deny(__('Cannot chat with yourself'));
        }

        // Check conversation limit
        $maxConversations = $user->getRestrictionValue(
            'chat.send',
            'max_conversations',
        );
        if (
            $maxConversations &&
            $user->chatConversations()->count() >= $maxConversations
        ) {
            return $this->deny(__('Conversation limit reached'), [
                'showUpgradeButton' => true,
            ]);
        }

        // Follower-limit check (if configured)
        $maxFollowers =
            $user->getRestrictionValue(
                'chat.send',
                'max_recipient_followers',
            ) ?? $this->settings->get('chat.follower_limit', null);

        if ($maxFollowers) {
            // Query count (using follows table)
            $followerCount = \DB::table('follows')
                ->where('followed_id', $recipient->id)
                ->count();

            if ($followerCount > $maxFollowers) {
                return $this->deny(
                    __(
                        'Can only chat with users who have fewer than :count followers',
                        ['count' => $maxFollowers],
                    ),
                );
            }
        }

        return true;
    }

    public function sendMessage(User $user, ChatConversation $conversation)
    {
        // Check if conversation is blocked
        if ($conversation->blocked_at) {
            // If specific user is blocked, check if current user is that user
            if ($conversation->blocked_user_id) {
                if ($conversation->blocked_user_id === $user->id) {
                    return $this->deny(__('You have been blocked from this conversation'));
                }
            } else {
                // Entire conversation is blocked
                return $this->deny(__('This conversation has been blocked'));
            }
        }

        // Check if user is participant
        if (
            !in_array($user->id, [
                $conversation->user_one_id,
                $conversation->user_two_id,
            ])
        ) {
            return $this->deny(__('You are not a participant'));
        }

        return $this->hasPermission($user, 'chat.send');
    }

    public function update(User $user, ChatMessage $message)
    {
        return $message->user_id === $user->id;
    }

    public function destroy(User $user, ChatMessage $message)
    {
        return $message->user_id === $user->id;
    }

    public function moderate(User $user)
    {
        return $this->hasPermission($user, 'chat.moderate');
    }
}
