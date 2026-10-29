<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Common\Core\BaseController;
use Common\Files\FileEntry;
use Common\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MessageController extends BaseController
{
    public function __construct(
        protected Request $request,
        protected Settings $settings,
    ) {
    }

    public function store(ChatConversation $conversation)
    {
        $startTime = microtime(true);
        $userId = $this->request->user()->id;

        if (config('app.debug')) {
            Log::debug('Chat message send attempt', [
                'user_id' => $userId,
                'conversation_id' => $conversation->id,
                'has_body' => $this->request->has('body'),
                'body_length' => $this->request->input('body')
                    ? strlen($this->request->input('body'))
                    : 0,
                'has_attachments' => $this->request->has('attachments'),
                'attachments_count' => count(
                    $this->request->input('attachments', []),
                ),
            ]);
        }

        try {
            $this->authorize('sendMessage', $conversation);
        } catch (\Exception $e) {
            Log::warning('Chat message authorization failed', [
                'user_id' => $userId,
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        try {
            $validated = $this->request->validate([
                'body' => [
                    'nullable',
                    'string',
                    'max:' . $this->settings->get('chat.max_message_length', 5000),
                ],
                'attachments' => 'nullable|array|max:5',
                'attachments.*' => 'integer|exists:file_entries,id',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Chat message validation failed', [
                'user_id' => $userId,
                'conversation_id' => $conversation->id,
                'errors' => $e->errors(),
                'input' => $this->request->all(),
            ]);
            throw $e;
        }

        // Require either body or attachments
        if (empty($validated['body']) && empty($validated['attachments'])) {
            Log::warning('Chat message validation failed: empty body and attachments', [
                'user_id' => $userId,
                'conversation_id' => $conversation->id,
            ]);
            return $this->error('Message body or attachments are required', ['body' => ['Message body or attachments are required']], 422);
        }

        // Verify attachments belong to user and correct type
        if (!empty($validated['attachments'])) {
            $attachments = FileEntry::whereIn('id', $validated['attachments'])
                ->where('owner_id', $this->request->user()->id)
                ->where('upload_type', 'chatAttachment')
                ->get();

            if ($attachments->count() !== count($validated['attachments'])) {
                Log::warning('Invalid attachments validation failed', [
                    'user_id' => $this->request->user()->id,
                    'requested_ids' => $validated['attachments'],
                    'found_count' => $attachments->count(),
                    'found_ids' => $attachments->pluck('id')->toArray(),
                    'found_entries' => FileEntry::whereIn('id', $validated['attachments'])->get(['id', 'owner_id', 'upload_type', 'type'])->toArray(),
                ]);
                return $this->error('Invalid attachments', [], 422);
            }
        }

        // Create message
        try {
            // Keep message create + attachments in one transaction so observer
            // after-commit hooks run with fully persisted message state.
            $message = DB::transaction(function () use (
                $conversation,
                $userId,
                $validated,
            ) {
                $message = $conversation->messages()->create([
                    'user_id' => $userId,
                    'body' => $validated['body'] ?? '',
                ]);

                if (!empty($validated['attachments'])) {
                    foreach ($validated['attachments'] as $attachmentId) {
                        $message->attachments()->attach($attachmentId);
                    }
                }

                return $message;
            });

            // Load relations for API response payload
            $message->load('user:id,name,image', 'attachments');

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            if (config('app.debug')) {
                Log::debug('Chat message sent successfully', [
                    'message_id' => $message->id,
                    'user_id' => $userId,
                    'conversation_id' => $conversation->id,
                    'body_length' => strlen($validated['body'] ?? ''),
                    'attachments_count' => count($validated['attachments'] ?? []),
                    'duration_ms' => $duration,
                ]);
            }

            return $this->success(['message' => $message]);
        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('Chat message creation failed', [
                'user_id' => $userId,
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'duration_ms' => $duration,
            ]);

            throw $e;
        }
    }

    public function update(ChatMessage $message)
    {
        $this->authorize('update', $message);

        $validated = $this->request->validate([
            'body' => [
                'required',
                'string',
                'max:' . $this->settings->get('chat.max_message_length', 5000),
            ],
        ]);

        $message->update([
            'body' => $validated['body'],
            'edited_at' => now(),
        ]);

        $message->load('user:id,name,image', 'attachments');

        return $this->success(['message' => $message]);
    }

    public function destroy(ChatMessage $message)
    {
        $this->authorize('destroy', $message);

        $message->delete();

        return $this->success(['message' => 'Message deleted']);
    }
}
