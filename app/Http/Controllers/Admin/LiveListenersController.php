<?php

namespace App\Http\Controllers\Admin;

use App\Models\TrackPlay;
use Common\Core\BaseController;
use Illuminate\Support\Carbon;

class LiveListenersController extends BaseController
{
    public function index()
    {
        abort_unless(
            auth()->user()?->hasPermission('admin'),
            403,
        );

        $since = Carbon::now()->subMinutes(5);

        $plays = TrackPlay::with([
            'track:id,name',
            'track.artists:id,name',
        ])
            ->with('user')
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        // one entry per listener (not per play): keep only the most
        // recent play for each user; guests are identified by their
        // approximate position + device fingerprint
        $plays = $plays->unique(function ($play) {
            if ($play->user_id) {
                return 'user:' . $play->user_id;
            }
            return 'guest:' .
                round((float) $play->lat, 3) .
                ',' .
                round((float) $play->lon, 3) .
                ':' .
                $play->browser .
                ':' .
                $play->device;
        })->values();

        $locationCounts = $plays
            ->groupBy('location')
            ->map(fn($group) => $group->count())
            ->filter(fn($count, $code) => !empty($code));

        $total = $locationCounts->sum();
        $locations = $locationCounts
            ->map(function ($count, $code) use ($total) {
                $name = $this->countryName($code);
                return [
                    'label' => $name,
                    'code' => $code,
                    'value' => $count,
                    'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
                ];
            })
            ->values()
            ->sortByDesc('value')
            ->values();

        $listeners = $plays->take(200)->map(function ($play) {
            return [
                'id' => $play->id,
                'track_name' => $play->track?->name ?? 'Unknown track',
                'artist_name' => $play->track?->artists?->first()?->name,
                'user_name' => $play->user?->name,
                'country_code' => $play->location,
                'country_name' => $this->countryName($play->location),
                'city' => $play->city,
                'lat' => $play->lat !== null ? (float) $play->lat : null,
                'lon' => $play->lon !== null ? (float) $play->lon : null,
                'created_at' => $play->created_at,
            ];
        });

        return $this->success([
            'total' => $plays->count(),
            'locations' => [
                'datasets' => [
                    [
                        'label' => 'Listeners',
                        'data' => $locations,
                    ],
                ],
            ],
            'listeners' => $listeners,
        ]);
    }

    protected function countryName(?string $code): ?string
    {
        if (!$code) {
            return null;
        }
        $formatter = class_exists('Locale')
            ? \Locale::getDisplayRegion('-' . strtoupper($code), 'en')
            : strtoupper($code);
        return $formatter ?: strtoupper($code);
    }
}
