<?php

namespace App\Services\Tracks;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Playlist;
use App\Models\Track;
use App\Models\TrackPlay;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Jenssegers\Agent\Agent;

class LogTrackPlay
{
    public function execute(
        Track $track,
        ?string $queueId,
        ?float $lat = null,
        ?float $lon = null,
    ): ?TrackPlay {
        // only log play every minute for same track and user
        $existing = $track
            ->plays()
            ->whereBetween('created_at', [
                Carbon::now()->subMinute(),
                Carbon::now(),
            ])
            ->where('user_id', Auth::id())
            ->first();
        if (!$existing) {
            return $this->log($track, $queueId, $lat, $lon);
        }
        return null;
    }

    private function log(
        Track $track,
        ?string $queueId,
        ?float $lat = null,
        ?float $lon = null,
    ): TrackPlay {
        $agent = app(Agent::class);
        $geo = geoip(getIp());
        $city = $geo['city'] ?? null;

        // if the browser gave us precise GPS coordinates, the IP-based city
        // name may not match anymore (e.g. mobile networks route through a
        // distant city) - resolve the real city for those coordinates
        if ($lat !== null && $lon !== null) {
            $city = $this->reverseGeocodeCity($lat, $lon) ?? $city;
        }

        $attributes = [
            'location' => strtolower($geo['iso_code'] ?? '') ?: null,
            // prefer the browser-reported GPS coordinates (opt-in, precise)
            // over the IP-based geoip estimate (always available, city-level)
            'lat' => $lat ?? $geo['lat'] ?? null,
            'lon' => $lon ?? $geo['lon'] ?? null,
            'city' => $city,
            'platform' => strtolower($agent->platform()),
            'device' => $this->getDevice(),
            'browser' => strtolower($agent->browser()),
            'user_id' => Auth::id(),
        ];

        $trackPlay = $track->plays()->create($attributes);

        Track::where('id', $track->id)->increment('plays');
        if ($track->album_id) {
            Album::where('id', $track->album_id)->increment('plays');
        }
        $artistIds = $track->artists->pluck('id');
        if ($artistIds->isNotEmpty()) {
            Artist::whereIn('id', $artistIds)->increment('plays');
        }

        [$modelType, $modelId] = array_pad(explode('.', $queueId), 2, null);
        if ($modelType === Playlist::MODEL_TYPE) {
            Playlist::where('id', $modelId)->increment('plays');
        }

        return $trackPlay;
    }

    private function getDevice(): string
    {
        $agent = app(Agent::class);
        if ($agent->isMobile()) {
            return 'mobile';
        } elseif ($agent->isTablet()) {
            return 'tablet';
        } else {
            return 'desktop';
        }
    }

    private function reverseGeocodeCity(float $lat, float $lon): ?string
    {
        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'User-Agent' => 'TuneMusics/1.0',
            ])
                ->timeout(2)
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'format' => 'json',
                    'lat' => $lat,
                    'lon' => $lon,
                    'zoom' => 10,
                ]);

            if (!$response->successful()) {
                return null;
            }

            $address = $response->json('address') ?? [];
            return $address['city'] ??
                $address['town'] ??
                $address['village'] ??
                $address['municipality'] ??
                null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
