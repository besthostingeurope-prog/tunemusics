<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\User;
use Common\Core\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConversationController extends BaseController
{
    public function __construct(protected Request $request)
    {
    }

    public function index()
    {
        if (config('app.debug')) {
            Log::debug('ConversationController::index called', [
                'user_id' => $this->request->user()?->id,
            ]);
        }

        try {
            $this->authorize('index', ChatConversation::class);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Log::error('ConversationController::index authorization failed', [
                'user_id' => $this->request->user()?->id,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }

        $user = $this->request->user();

        $conversations = ChatConversation::query()
            ->where(function ($query) use ($user) {
                $query
                    ->where('user_one_id', $user->id)
                    ->whereNull('user_one_archived_at');
            })
            ->orWhere(function ($query) use ($user) {
                $query
                    ->where('user_two_id', $user->id)
                    ->whereNull('user_two_archived_at');
            })
            ->with(['userOne:id,name,username,email,image', 'userTwo:id,name,username,email,image'])
            ->orderBy('last_message_at', 'desc')
            ->cursorPaginate(20);

        // Add unread count for current user to each conversation
        $conversations->getCollection()->transform(function ($conversation) use (
            $user,
        ) {
            $conversation->unread_count = $conversation->unreadCountFor($user);
            $otherUser = $conversation->getOtherUser($user);
            // Use username if available, otherwise name, otherwise email prefix
            $displayName = $otherUser->username ?? $otherUser->name;
            if (empty($displayName)) {
                $displayName = $otherUser->email ? explode('@', $otherUser->email)[0] : "User {$otherUser->id}";
            }
            $conversation->other_user = [
                'id' => $otherUser->id,
                'name' => $displayName,
                'username' => $otherUser->username,
                'image' => $otherUser->image,
            ];
            return $conversation;
        });

        return $this->success(['conversations' => $conversations]);
    }

    public function store()
    {
        $validated = $this->request->validate([
            'recipient_id' => 'required|integer|exists:users,id',
        ]);

        $recipient = User::findOrFail($validated['recipient_id']);

        $this->authorize('store', [ChatConversation::class, $recipient]);

        // Use transaction to prevent race conditions
        $conversation = DB::transaction(function () use ($recipient) {
            return ChatConversation::createOrGet(
                $this->request->user()->id,
                $recipient->id,
            );
        });

        $conversation->load(['userOne:id,name,image', 'userTwo:id,name,image']);

        return $this->success(['conversation' => $conversation]);
    }

    public function show(ChatConversation $conversation)
    {
        $user = $this->request->user();

        // Check if user is participant
        if (
            !in_array($user->id, [
                $conversation->user_one_id,
                $conversation->user_two_id,
            ])
        ) {
            abort(403, 'You are not a participant in this conversation');
        }

        // Load messages with pagination
        // Use simplePaginate to avoid cursor pagination issues with empty results
        $perPage = min((int) $this->request->get('per_page', 50), 100);
        $messages = $conversation
            ->messages()
            ->whereNull('deleted_at')
            ->with(['user:id,name,username,image', 'attachments'])
            ->orderBy('created_at', 'desc')
            ->simplePaginate($perPage);

        // Load conversation participants
        $conversation->load([
            'userOne:id,name,username,email,image',
            'userTwo:id,name,username,email,image',
        ]);

        // Add other user info - ensure it's properly serialized
        $otherUser = $conversation->getOtherUser($user);
        $displayName = $otherUser->username ?? $otherUser->name;
        if (empty($displayName)) {
            $displayName = $otherUser->email ? explode('@', $otherUser->email)[0] : "User {$otherUser->id}";
        }
        $conversation->other_user = [
            'id' => $otherUser->id,
            'name' => $displayName,
            'username' => $otherUser->username,
            'image' => $otherUser->image,
        ];
        $conversation->unread_count = $conversation->unreadCountFor($user);

        return $this->success([
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }

    public function markAsRead(ChatConversation $conversation)
    {
        $user = $this->request->user();

        // Check if user is participant
        if (
            !in_array($user->id, [
                $conversation->user_one_id,
                $conversation->user_two_id,
            ])
        ) {
            abort(403, 'You are not a participant in this conversation');
        }

        $conversation->markAsReadFor($user);

        return $this->success(['message' => 'Conversation marked as read']);
    }

    public function searchUsers()
    {
        $this->authorize('index', ChatConversation::class);

        $query = trim($this->request->get('query', ''));
        $perPage = min((int) $this->request->get('perPage', 10), 20);
        $currentUserId = $this->request->user()->id;

        if (config('app.debug')) {
            Log::debug('ConversationController::searchUsers called', [
                'query' => $query,
                'per_page' => $perPage,
                'current_user_id' => $currentUserId,
            ]);
        }

        if (strlen($query) < 2) {
            return $this->success(['pagination' => [
                'data' => [],
                'current_page' => 1,
                'per_page' => $perPage,
                'total' => 0,
            ]]);
        }

        // Search users directly by name, username, and email (case-insensitive)
        $userQuery = User::query()
            ->where('id', '!=', $currentUserId)
            ->where(function ($q) use ($query) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($query) . '%'])
                  ->orWhereRaw('LOWER(username) LIKE ?', ['%' . strtolower($query) . '%'])
                  ->orWhereRaw('LOWER(email) LIKE ?', ['%' . strtolower($query) . '%']);
            });

        $userIds = $userQuery->pluck('id')->toArray();

        // Search artists by name and get associated users (case-insensitive)
        $artistQuery = User::query()
            ->where('id', '!=', $currentUserId)
            ->whereHas('artists', function ($q) use ($query) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($query) . '%']);
            });

        $artistUserIds = $artistQuery->pluck('id')->toArray();

        // Combine and deduplicate user IDs
        $allUserIds = array_unique(array_merge($userIds, $artistUserIds));

        if (empty($allUserIds)) {
            return $this->success(['pagination' => [
                'data' => [],
                'current_page' => 1,
                'per_page' => $perPage,
                'total' => 0,
            ]]);
        }

        // Get users with pagination
        $users = User::query()
            ->whereIn('id', $allUserIds)
            ->with(['artists' => function ($query) {
                $query->wherePivot('role', 'artist')->limit(1);
            }])
            ->select('id', 'name', 'username', 'email', 'image')
            ->orderByRaw('COALESCE(username, name, email)')
            ->simplePaginate($perPage);

        $transformedUsers = $users->getCollection()->map(function ($user) {
            $primaryArtist = $user->artists->first();
            // Handle display name - prefer username, then name, then email
            $displayName = $user->username ?? $user->name;
            if (empty($displayName)) {
                $displayName = $user->email ?? "User {$user->id}";
            }
            return [
                'id' => $user->id,
                'name' => $displayName,
                'image' => $user->image,
                'artist_id' => $primaryArtist?->id,
            ];
        })->values()->all();

        $response = [
            'pagination' => [
                'data' => $transformedUsers,
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => count($allUserIds),
            ],
        ];

        return $this->success($response);
    }

    public function archive(ChatConversation $conversation)
    {
        $user = $this->request->user();

        // Check if user is participant
        if (
            !in_array($user->id, [
                $conversation->user_one_id,
                $conversation->user_two_id,
            ])
        ) {
            abort(403, 'You are not a participant in this conversation');
        }

        if ($conversation->user_one_id === $user->id) {
            $conversation->update([
                'user_one_archived_at' => now(),
            ]);
        } else {
            $conversation->update([
                'user_two_archived_at' => now(),
            ]);
        }

        return $this->success(['message' => 'Conversation archived']);
    }

    public function mute(ChatConversation $conversation)
    {
        $user = $this->request->user();

        // Check if user is participant
        if (
            !in_array($user->id, [
                $conversation->user_one_id,
                $conversation->user_two_id,
            ])
        ) {
            abort(403, 'You are not a participant in this conversation');
        }

        $mute = $this->request->input('mute', true);

        if ($conversation->user_one_id === $user->id) {
            $conversation->update([
                'user_one_muted_at' => $mute ? now() : null,
            ]);
        } else {
            $conversation->update([
                'user_two_muted_at' => $mute ? now() : null,
            ]);
        }

        return $this->success([
            'message' => $mute ? 'Conversation muted' : 'Conversation unmuted',
        ]);
    }
}
