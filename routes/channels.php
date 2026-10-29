<?php

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('App.Conversation.{conversationId}', function (
    $user,
    $conversationId,
) {
    // Use cache to reduce database queries for authorization
    $cacheKey = "chat_auth:{$user->id}:{$conversationId}";

    return cache()->remember($cacheKey, 300, function () use ($user, $conversationId) {
        $conversation = \App\Models\ChatConversation::select(['id', 'user_one_id', 'user_two_id'])
            ->where('id', $conversationId)
            ->first();

        if (!$conversation) {
            return false;
        }

        return in_array($user->id, [
            $conversation->user_one_id,
            $conversation->user_two_id,
        ]);
    });
});
