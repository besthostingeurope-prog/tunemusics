<?php

namespace App\Services\Providers\Spotify;

use App\Services\Providers\DataObjects\NormalizedPlaylist;
use App\Traits\AuthorizesWithSpotify;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SpotifyPlaylist extends SpotifyBase
{
    use AuthorizesWithSpotify;

    public function getContent(string $playlistId): ?NormalizedPlaylist
    {
        @ini_set('max_execution_time', 0);

        $response = $this->getResponseData(
            Http::withHeaders([
                'Authorization' => "Bearer {$this->authorize()}",
            ])->get("{$this->baseUrl}/playlists/$playlistId"),
        );
        if (isset($response['tracks']['items'])) {
            return $this->normalizer->playlist($response);
        }

        // spotify restricts the /playlists endpoint for dev-mode apps; fall
        // back to the public embed page for the track list, then load full
        // track data through the still-available /tracks endpoint
        return $this->getContentViaEmbed($playlistId);
    }

    protected function getContentViaEmbed(
        string $playlistId,
    ): ?NormalizedPlaylist {
        try {
            $html = Http::withHeaders([
                'User-Agent' =>
                    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
            ])
                ->timeout(20)
                ->get("https://open.spotify.com/embed/playlist/$playlistId")
                ->body();

            if (
                !preg_match(
                    '/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s',
                    $html,
                    $m,
                )
            ) {
                Log::error('Spotify embed fallback: no __NEXT_DATA__ found');
                return null;
            }

            $json = json_decode($m[1], true);
            $entity = Arr::get($json, 'props.pageProps.state.data.entity');
            if (!$entity) {
                Log::error('Spotify embed fallback: no entity in payload');
                return null;
            }

            $trackIds = collect(Arr::get($entity, 'trackList', []))
                ->map(
                    fn($t) => Str::afterLast(Arr::get($t, 'uri', ''), ':') ?:
                    null,
                )
                ->filter()
                ->unique()
                ->values();

            if ($trackIds->isEmpty()) {
                Log::error('Spotify embed fallback: no track ids');
                return null;
            }

            $normalizedTracks = [];
            foreach ($trackIds->chunk(50) as $chunk) {
                $data = $this->getResponseData(
                    Http::withHeaders([
                        'Authorization' => "Bearer {$this->authorize()}",
                    ])->get(
                        "{$this->baseUrl}/tracks?ids=" . $chunk->implode(','),
                    ),
                );
                foreach (Arr::get($data, 'tracks', []) as $spotifyTrack) {
                    if ($spotifyTrack && isset($spotifyTrack['id'])) {
                        $normalizedTracks[] = $this->normalizer->track(
                            $spotifyTrack,
                        );
                    }
                }
            }

            if (empty($normalizedTracks)) {
                Log::error(
                    'Spotify embed fallback: /tracks endpoint returned nothing',
                );
                return null;
            }

            $images = Arr::get($entity, 'coverArt.sources', []);
            $image = !empty($images)
                ? Arr::get(end($images), 'url')
                : null;

            return new NormalizedPlaylist(
                externalIdName: 'spotify_id',
                externalId: $playlistId,
                name: Str::limit(Arr::get($entity, 'name') ?? 'Playlist', 188),
                description: Str::limit(
                    strip_tags(Arr::get($entity, 'subtitle') ?? ''),
                    188,
                ),
                image: $image,
                tracks: $normalizedTracks,
            );
        } catch (\Throwable $e) {
            Log::error('Spotify embed fallback error: ' . $e->getMessage());
            return null;
        }
    }
}
