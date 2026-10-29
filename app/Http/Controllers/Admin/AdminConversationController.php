<?php

namespace App\Http\Controllers\Admin;

use App\Models\ChatConversation;
use Common\Core\BaseController;
use Illuminate\Http\Request;

class AdminConversationController extends BaseController
{
    public function __construct(protected Request $request)
    {
    }

    public function index()
    {
        $this->authorize('moderate', ChatConversation::class);

        $query = ChatConversation::query()
            ->with(['userOne:id,name,username,email,image', 'userTwo:id,name,username,email,image'])
            ->orderBy('last_message_at', 'desc');

        // Filter by blocked status
        if ($this->request->has('blocked')) {
            if ($this->request->input('blocked') === 'true') {
                $query->whereNotNull('blocked_at');
            } else {
                $query->whereNull('blocked_at');
            }
        }

        // Search by user (ID, name, or email)
        if ($this->request->has('user_search')) {
            $searchTerm = $this->request->input('user_search');
            
            // If numeric, treat as user ID
            if (is_numeric($searchTerm)) {
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('user_one_id', $searchTerm)
                        ->orWhere('user_two_id', $searchTerm);
                });
            } else {
                // Search by name or email
                $query->where(function ($q) use ($searchTerm) {
                    $q->whereHas('userOne', function ($userQuery) use ($searchTerm) {
                        $userQuery->where('name', 'like', "%{$searchTerm}%")
                            ->orWhere('email', 'like', "%{$searchTerm}%");
                    })->orWhereHas('userTwo', function ($userQuery) use ($searchTerm) {
                        $userQuery->where('name', 'like', "%{$searchTerm}%")
                            ->orWhere('email', 'like', "%{$searchTerm}%");
                    });
                });
            }
        }
        
        // Legacy support for user_id parameter
        if ($this->request->has('user_id')) {
            $userId = $this->request->input('user_id');
            $query->where(function ($q) use ($userId) {
                $q->where('user_one_id', $userId)->orWhere(
                    'user_two_id',
                    $userId,
                );
            });
        }

        $conversations = $query->cursorPaginate(20);

        $conversations->getCollection()->transform(function ($conversation) {
            if (!$conversation->relationLoaded('userOne')) {
                $conversation->load('userOne:id,name,username,email,image');
            }
            if (!$conversation->relationLoaded('userTwo')) {
                $conversation->load('userTwo:id,name,username,email,image');
            }
            return $conversation;
        });

        return $this->success(['conversations' => $conversations]);
    }

    public function show(ChatConversation $conversation)
    {
        $this->authorize('moderate', ChatConversation::class);

        // Load messages
        $messages = $conversation
            ->messages()
            ->withTrashed()
            ->with(['user:id,name,image', 'attachments'])
            ->orderBy('created_at', 'desc')
            ->cursorPaginate(50);

        // Load conversation participants
        $conversation->load([
            'userOne:id,name,username,email,image',
            'userTwo:id,name,username,email,image',
            'blockedBy:id,name,image',
        ]);

        return $this->success([
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }

    public function block(ChatConversation $conversation)
    {
        $this->authorize('moderate', ChatConversation::class);

        $validated = $this->request->validate([
            'block' => 'required|boolean',
            'user_id' => 'nullable|integer|in:' . $conversation->user_one_id . ',' . $conversation->user_two_id,
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validated['block']) {
            $updateData = [
                'blocked_at' => now(),
                'blocked_by' => $this->request->user()->id,
                'blocked_reason' => $validated['reason'] ?? null,
            ];

            if (isset($validated['user_id'])) {
                $updateData['blocked_user_id'] = $validated['user_id'];
                $blockedUser = $conversation->user_one_id === $validated['user_id']
                    ? $conversation->userOne
                    : $conversation->userTwo;
                $message = $blockedUser
                    ? "User {$blockedUser->name} (ID: {$validated['user_id']}) blocked from this conversation"
                    : "User ID {$validated['user_id']} blocked from this conversation";
            } else {
                $updateData['blocked_user_id'] = null;
                $message = 'Conversation blocked (both users cannot send messages)';
            }

            $conversation->update($updateData);

            return $this->success(['message' => $message]);
        } else {
            $conversation->update([
                'blocked_at' => null,
                'blocked_by' => null,
                'blocked_user_id' => null,
                'blocked_reason' => null,
            ]);

            return $this->success(['message' => 'Conversation unblocked']);
        }
    }
}
