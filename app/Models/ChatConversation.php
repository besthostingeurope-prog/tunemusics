<?php

namespace App\Models;

use Common\Core\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class ChatConversation extends BaseModel
{
    protected $guarded = [];

    protected $casts = [
        'last_message_at' => 'datetime',
        'user_one_read_at' => 'datetime',
        'user_two_read_at' => 'datetime',
        'user_one_last_read_message_id' => 'integer',
        'user_two_last_read_message_id' => 'integer',
        'user_one_archived_at' => 'datetime',
        'user_two_archived_at' => 'datetime',
        'user_one_muted_at' => 'datetime',
        'user_two_muted_at' => 'datetime',
        'blocked_at' => 'datetime',
        'blocked_user_id' => 'integer',
    ];

    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'last_message_id');
    }

    /**
     * Get the other participant in the conversation
     */
    public function getOtherUser(User $user): User
    {
        return $this->user_one_id === $user->id ? $this->userTwo : $this->userOne;
    }

    /**
     * Get unread count for a specific user (O(1) with counter cache)
     */
    public function unreadCountFor(User $user): int
    {
        return $this->user_one_id === $user->id
            ? $this->user_one_unread_count
            : $this->user_two_unread_count;
    }

    /**
     * Mark conversation as read for a specific user
     */
    public function markAsReadFor(User $user): void
    {
        if ($this->user_one_id === $user->id) {
            $this->update([
                'user_one_read_at' => now(),
                'user_one_last_read_message_id' => $this->last_message_id,
                'user_one_unread_count' => 0,
            ]);
        } else {
            $this->update([
                'user_two_read_at' => now(),
                'user_two_last_read_message_id' => $this->last_message_id,
                'user_two_unread_count' => 0,
            ]);
        }
    }

    /**
     * Increment unread count for recipient (called from observer)
     */
    public function incrementUnreadFor(User $recipient): void
    {
        if ($this->user_one_id === $recipient->id) {
            $this->increment('user_one_unread_count');
        } else {
            $this->increment('user_two_unread_count');
        }
    }

    /**
     * Scope: active (non-archived) conversations for user
     */
    public function scopeActiveFor($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('user_one_id', $user->id)->whereNull(
                'user_one_archived_at',
            );
        })->orWhere(function ($q) use ($user) {
            $q->where('user_two_id', $user->id)->whereNull(
                'user_two_archived_at',
            );
        });
    }

    /**
     * Recalculate unread counts (for cleanup/recovery)
     */
    public function recalculateUnreadCounts(): void
    {
        // Use message IDs if available (more accurate), fallback to timestamps
        if ($this->user_one_last_read_message_id) {
            $userOneUnread = $this->messages()
                ->where('user_id', $this->user_two_id)
                ->where('id', '>', $this->user_one_last_read_message_id)
                ->whereNull('deleted_at')
                ->count();
        } else {
            $userOneUnread = $this->messages()
                ->where('user_id', $this->user_two_id)
                ->where(
                    'created_at',
                    '>',
                    $this->user_one_read_at ?? now()->subYears(10),
                )
                ->whereNull('deleted_at')
                ->count();
        }

        if ($this->user_two_last_read_message_id) {
            $userTwoUnread = $this->messages()
                ->where('user_id', $this->user_one_id)
                ->where('id', '>', $this->user_two_last_read_message_id)
                ->whereNull('deleted_at')
                ->count();
        } else {
            $userTwoUnread = $this->messages()
                ->where('user_id', $this->user_one_id)
                ->where(
                    'created_at',
                    '>',
                    $this->user_two_read_at ?? now()->subYears(10),
                )
                ->whereNull('deleted_at')
                ->count();
        }

        $this->update([
            'user_one_unread_count' => $userOneUnread,
            'user_two_unread_count' => $userTwoUnread,
        ]);
    }

    /**
     * Create or get conversation (prevents self-chat and ensures ordering)
     */
    public static function createOrGet(int $userOneId, int $userTwoId): self
    {
        // Prevent self-chat
        if ($userOneId === $userTwoId) {
            throw new InvalidArgumentException(
                'Cannot create conversation with self',
            );
        }

        // Ensure user_one_id is always lower
        [$lower, $higher] = $userOneId < $userTwoId
            ? [$userOneId, $userTwoId]
            : [$userTwoId, $userOneId];

        return self::firstOrCreate([
            'user_one_id' => $lower,
            'user_two_id' => $higher,
        ]);
    }

    public static function filterableFields(): array
    {
        return ['id', 'user_one_id', 'user_two_id'];
    }

    public function toNormalizedArray(): array
    {
        return [
            'id' => $this->id,
            'user_one_id' => $this->user_one_id,
            'user_two_id' => $this->user_two_id,
            'last_message_at' => $this->last_message_at,
            'created_at' => $this->created_at,
        ];
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
        ];
    }

    public static function getModelTypeAttribute(): string
    {
        return 'chatConversation';
    }
}
