<?php

namespace App\Policies;

use App\Models\User;
use Common\Core\Policies\FileEntryPolicy;
use Common\Files\FileEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MusicUploadPolicy extends FileEntryPolicy
{
    public function show(?User $user, FileEntry $entry): bool
    {
        // Allow chat participants to view chat attachments
        if ($user && $entry->upload_type === 'chatAttachment') {
            $isParticipant = DB::table('file_entry_models')
                ->join('chat_messages', 'chat_messages.id', '=', 'file_entry_models.model_id')
                ->join('chat_conversations', 'chat_conversations.id', '=', 'chat_messages.conversation_id')
                ->where('file_entry_models.file_entry_id', $entry->id)
                ->where('file_entry_models.relation_type', 'chatAttachment')
                ->whereNull('chat_messages.deleted_at')
                ->where(function ($q) use ($user) {
                    $q->where('chat_conversations.user_one_id', $user->id)
                        ->orWhere('chat_conversations.user_two_id', $user->id);
                })
                ->exists();

            if ($isParticipant) {
                return true;
            }
        }

        return parent::show($user, $entry);
    }

    public function download(User $user, $entries): bool
    {
        $entriesCollection = $entries instanceof Collection
            ? $entries
            : collect($entries);

        // Allow chat participants to download chat attachments as well.
        if (
            $entriesCollection->isNotEmpty() &&
            $entriesCollection->every(
                fn($entry) =>
                    $entry instanceof FileEntry &&
                    $entry->upload_type === 'chatAttachment',
            )
        ) {
            $entryIds = $entriesCollection->pluck('id')->values();

            $allowedEntryIds = DB::table('file_entry_models')
                ->join(
                    'chat_messages',
                    'chat_messages.id',
                    '=',
                    'file_entry_models.model_id',
                )
                ->join(
                    'chat_conversations',
                    'chat_conversations.id',
                    '=',
                    'chat_messages.conversation_id',
                )
                ->whereIn('file_entry_models.file_entry_id', $entryIds)
                ->where('file_entry_models.relation_type', 'chatAttachment')
                ->whereNull('chat_messages.deleted_at')
                ->where(function ($q) use ($user) {
                    $q->where('chat_conversations.user_one_id', $user->id)
                        ->orWhere('chat_conversations.user_two_id', $user->id);
                })
                ->distinct()
                ->pluck('file_entry_models.file_entry_id');

            if ($allowedEntryIds->count() === $entryIds->count()) {
                return true;
            }
        }

        return parent::download($user, $entries);
    }

    public function store(
        User $user,
        int|null $parentId = null,
        string|null $uploadType = null,
    ): bool {
        if (!$uploadType) {
            $uploadType = request('uploadType');
        }

        if (
            in_array($uploadType, ['media', 'artwork']) &&
            ($this->hasPermission($user, 'music.create') ||
                $this->hasPermission($user, 'music.update'))
        ) {
            return true;
        }

        if (
            $uploadType === 'backstageAttachments' &&
            $this->hasPermission($user, 'backstageRequests.create')
        ) {
            return true;
        }

        return parent::store($user, $parentId, $uploadType);
    }
}
