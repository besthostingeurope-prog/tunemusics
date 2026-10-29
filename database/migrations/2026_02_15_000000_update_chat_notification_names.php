<?php

use App\Models\ChatMessage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $rows = DB::table('notifications')
            ->where('type', 'App\\Notifications\\NewChatMessage')
            ->get();

        foreach ($rows as $row) {
            $data = json_decode($row->data, true);
            if (!$data || ($data['sender_name'] ?? '') !== 'Deleted User') {
                continue;
            }
            $message = ChatMessage::query()
                ->with('user:id,name,username,email')
                ->find($data['message_id'] ?? null);
            if (!$message?->user) {
                continue;
            }
            $u = $message->user;
            $name = $u->name ?? $u->username ?? ($u->email ? explode('@', $u->email)[0] : null) ?? 'Unknown user';
            $data['sender_name'] = $name;
            $data['lines'][0]['content'] = __(':username sent you a message:', ['username' => $name]);
            DB::table('notifications')->where('id', $row->id)->update(['data' => json_encode($data)]);
        }
    }

    public function down(): void
    {
    }
};
