<?php

namespace App\Models;

use Common\Core\BaseModel;
use Common\Files\Traits\HasAttachedFileEntries;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatMessage extends BaseModel
{
    use SoftDeletes, HasAttachedFileEntries;

    protected $guarded = [];

    protected $casts = [
        'edited_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get file attachments via polymorphic relation
     */
    public function attachments()
    {
        return $this->attachedFileEntriesRelation('chatAttachment');
    }

    public static function filterableFields(): array
    {
        return ['id', 'conversation_id', 'user_id'];
    }

    public function toNormalizedArray(): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'user_id' => $this->user_id,
            'body' => $this->body,
            'edited_at' => $this->edited_at,
            'created_at' => $this->created_at,
        ];
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
        ];
    }

    public static function getModelTypeAttribute(): string
    {
        return 'chatMessage';
    }
}
