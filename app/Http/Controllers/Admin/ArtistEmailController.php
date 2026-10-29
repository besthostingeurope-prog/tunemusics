<?php

namespace App\Http\Controllers\Admin;

use App\Mail\ArtistAnnouncement;
use App\Models\ArtistEarning;
use App\Models\ArtistEmailLog;
use App\Models\User;
use Common\Core\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ArtistEmailController extends BaseController
{
    protected function ensureAdmin(): void
    {
        abort_unless(
            auth()->user()?->hasPermission('admin'),
            403,
        );
    }

    protected function applyPlaceholders(string $text, User $user): string
    {
        $artist = $user->artists()->first();
        $artistName = $artist?->name ?: ($user->name ?? 'artist');

        $cents = 0;
        if ($artist) {
            $cents = ArtistEarning::where('artist_id', $artist->id)
                ->whereIn('status', ['pending', 'accrued'])
                ->sum('net_amount');
        }
        $amount = '$' . number_format($cents / 100, 2);

        return str_replace(
            ['{{artist_name}}', '{{name}}', '{{email}}', '{{earnings_amount}}'],
            [$artistName, $user->name ?? 'artist', $user->email, $amount],
            $text,
        );
    }

    public function recipients()
    {
        $this->ensureAdmin();

        $users = User::whereHas('artists')
            ->whereNotNull('email')
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return $this->success(['users' => $users]);
    }

    public function history()
    {
        $this->ensureAdmin();

        $logs = ArtistEmailLog::orderByDesc('id')->limit(50)->get();

        return $this->success(['logs' => $logs]);
    }

    public function preview(Request $request)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'body' => 'required|string|max:20000',
            'html' => 'boolean',
        ]);

        $sampleUser =
            User::whereHas('artists')->whereNotNull('email')->first() ??
            auth()->user();

        $html = view('emails.artist-announcement', [
            'bodyText' => $this->applyPlaceholders($data['body'], $sampleUser),
            'recipientName' => $sampleUser->name ?? 'Artist',
            'isHtml' => !empty($data['html']),
        ])->render();

        return $this->success(['html' => $html]);
    }

    public function send(Request $request)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'subject' => 'required|string|max:150',
            'body' => 'required|string|max:20000',
            'html' => 'boolean',
            'all' => 'boolean',
            'user_ids' => 'array',
            'user_ids.*' => 'integer',
        ]);

        $query = User::whereHas('artists')->whereNotNull('email');

        if (empty($data['all'])) {
            abort_if(
                empty($data['user_ids']),
                422,
                'Select at least one artist.',
            );
            $query->whereIn('id', $data['user_ids']);
        }

        $users = $query->get();
        $sent = 0;
        $failed = [];

        foreach ($users as $user) {
            try {
                Mail::to($user->email)->send(
                    new ArtistAnnouncement(
                        $this->applyPlaceholders($data['subject'], $user),
                        $this->applyPlaceholders($data['body'], $user),
                        $user->name ?? 'artist',
                        !empty($data['html']),
                    ),
                );
                $sent++;
            } catch (\Throwable $e) {
                $failed[] = $user->email;
                Log::error(
                    'Artist email failed for ' .
                        $user->email .
                        ': ' .
                        $e->getMessage(),
                );
            }
        }

        ArtistEmailLog::create([
            'subject' => $data['subject'],
            'body' => $data['body'],
            'is_html' => !empty($data['html']),
            'sent_count' => $sent,
            'total_count' => $users->count(),
            'recipients' => json_encode($users->pluck('email')->values()),
            'failed' => json_encode($failed),
            'sent_by' => auth()->id(),
        ]);

        return $this->success([
            'sent' => $sent,
            'total' => $users->count(),
            'failed' => $failed,
        ]);
    }
}
