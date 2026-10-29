<?php

namespace App\Http\Controllers;

use App\Models\Artist;
use App\Models\Genre;
use App\Models\Track;
use App\Services\Artists\ArtistLoader;
use App\Services\Genres\GenreToApiResource;
use App\Services\Providers\MusicMetadataProvider;
use App\Services\Providers\Spotify\SpotifyRadio;
use App\Services\Tracks\TrackLoader;
use Carbon\Carbon;
use Common\Core\BaseController;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RadioController extends BaseController
{
    public function getRecommendations(string $modelType, int $modelId): array
    {
        $model = $this->findModel($modelType, $modelId);

        $this->authorize('index', Track::class);

        $recommendations = Cache::remember(
            "radio.$model->getMorphClass().{$model['id']}",
            Carbon::now()->addDays(2),
            function () use ($model, $modelType) {
                $tracks = (new MusicMetadataProvider())->getRecommendations(
                    $model,
                );

                if ($tracks->isEmpty()) {
                    $tracks = $this->getGenreFallbackRecommendations(
                        $model,
                        $modelType,
                    );
                }

                if ($tracks->isEmpty()) {
                    $tracks = $this->getCollaboratorFallbackRecommendations(
                        $model,
                        $modelType,
                    );
                }

                if ($tracks->isEmpty()) {
                    $tracks = $this->getPopularFallbackRecommendations(
                        $model,
                        $modelType,
                    );
                }

                $tracks = $tracks->map(
                    fn(Track $track) => (new TrackLoader())->toApiResource(
                        $track,
                    ),
                );

                return empty($tracks) ? null : $tracks;
            },
        );

        $seed = match ($modelType) {
            'artist' => (new ArtistLoader())->toApiResource($model),
            'genre' => (new GenreToApiResource())->execute($model),
            'track' => (new TrackLoader())->toApiResource($model),
        };

        $isLiked =
            Auth::check() &&
            DB::table('likes')
                ->where('user_id', Auth::id())
                ->where('likeable_type', "{$modelType}_radio")
                ->where('likeable_id', $modelId)
                ->exists();

        return [
            'type' => $modelType,
            'seed' => $seed,
            'recommendations' => $recommendations ?: [],
            'isLiked' => $isLiked,
        ];
    }

    /**
     * Fallback for artists/tracks with no external (Spotify) metadata link:
     * recommend the most popular tracks from this site's own catalog that
     * share a genre with the seed.
     */
    protected function getGenreFallbackRecommendations(
        $model,
        string $modelType,
    ): Collection {
        $genreIds = match ($modelType) {
            'genre' => collect([$model->id]),
            'track' => collect()
                ->merge($model->genres->pluck('id'))
                ->merge($model->album?->genres->pluck('id') ?? [])
                ->merge(
                    $model->artists->flatMap(
                        fn($artist) => $artist->genres->pluck('id'),
                    ),
                )
                ->unique(),
            'artist' => $model->genres->pluck('id'),
            default => collect(),
        };

        if ($genreIds->isEmpty()) {
            return new Collection();
        }

        $excludeTrackId = $modelType === 'track' ? $model->id : null;

        return Track::query()
            ->where(function ($query) use ($genreIds) {
                // genre tags mostly live on albums/artists, not individual
                // tracks, so match on any of the three
                $query
                    ->whereHas(
                        'genres',
                        fn($q) => $q->whereIn('genres.id', $genreIds),
                    )
                    ->orWhereHas(
                        'album.genres',
                        fn($q) => $q->whereIn('genres.id', $genreIds),
                    )
                    ->orWhereHas(
                        'artists.genres',
                        fn($q) => $q->whereIn('genres.id', $genreIds),
                    );
            })
            ->when(
                $excludeTrackId,
                fn($query) => $query->where('tracks.id', '!=', $excludeTrackId),
            )
            ->with(['album.artists', 'artists'])
            ->orderByDesc('plays')
            ->limit(30)
            ->get();
    }

    /**
     * Second fallback: recommend popular tracks by artists who have
     * collaborated (appeared on the same track) with this artist. Useful
     * when the artist has no genre tags at all.
     */
    protected function getCollaboratorFallbackRecommendations(
        $model,
        string $modelType,
    ): Collection {
        if ($modelType !== 'artist' && $modelType !== 'track') {
            return new Collection();
        }

        $artistIds =
            $modelType === 'artist'
                ? collect([$model->id])
                : $model->artists->pluck('id');

        if ($artistIds->isEmpty()) {
            return new Collection();
        }

        $collaboratorIds = Artist::whereHas(
            'tracks',
            fn($q) => $q->whereHas(
                'artists',
                fn($q2) => $q2->whereIn('artists.id', $artistIds),
            ),
        )
            ->whereNotIn('id', $artistIds)
            ->pluck('id');

        if ($collaboratorIds->isEmpty()) {
            return new Collection();
        }

        $excludeTrackId = $modelType === 'track' ? $model->id : null;

        return Track::query()
            ->whereHas(
                'artists',
                fn($q) => $q->whereIn('artists.id', $collaboratorIds),
            )
            ->when(
                $excludeTrackId,
                fn($query) => $query->where('tracks.id', '!=', $excludeTrackId),
            )
            ->with(['album.artists', 'artists'])
            ->orderByDesc('plays')
            ->limit(30)
            ->get();
    }

    /**
     * Last-resort fallback so a radio page is never completely empty:
     * the most popular tracks on the site overall.
     */
    protected function getPopularFallbackRecommendations(
        $model,
        string $modelType,
    ): Collection {
        $excludeTrackId = $modelType === 'track' ? $model->id : null;

        return Track::query()
            ->when(
                $excludeTrackId,
                fn($query) => $query->where('tracks.id', '!=', $excludeTrackId),
            )
            ->with(['album.artists', 'artists'])
            ->orderByDesc('plays')
            ->limit(30)
            ->get();
    }

    private function findModel(string $type, int $modelId)
    {
        if ($type === 'artist') {
            return Artist::findOrFail($modelId);
        } elseif ($type === 'genre') {
            return Genre::findOrFail($modelId);
        } elseif ($type === 'track') {
            return Track::with('album.artists', 'artists')->findOrFail(
                $modelId,
            );
        }

        abort(404, 'Invalid radio seed');
    }
}
