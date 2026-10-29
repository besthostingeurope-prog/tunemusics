<?php

namespace App\Services\Artists;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Genre;
use App\Models\ProfileImage;
use App\Models\ProfileLink;
use App\Models\Track;
use App\Models\User;
use App\Services\Albums\AlbumLoader;
use App\Services\Albums\PaginateAlbums;
use App\Services\Genres\GenreToApiResource;
use App\Services\Providers\MusicMetadataProvider;
use App\Services\Tracks\PaginateTracks;
use App\Services\Tracks\TrackLoader;
use App\Services\Users\UserProfileLoader;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ArtistLoader
{
    public static array $artistPageTabs = [
        'discography' => 1,
        'similar' => 2,
        'about' => 3,
        'tracks' => 4,
        'albums' => 5,
        'followers' => 6,
    ];

    public function load(Artist $artist, string $loader): array
    {
        if ($artist->disabled && $loader !== 'editArtistPage') {
            abort(404);
        }

        $response = [
            'artist' => $artist,
            'loader' => $loader,
            'selectedAlbumViewMode' => Arr::get(
                $_COOKIE,
                'artistPage-albumViewMode',
                settings('player.default_artist_view', 'list'),
            ),
        ];

        if ($loader === 'artistPage') {
            if (
                $artist->needsUpdating() &&
                config('app.allow_live_scrape', false) &&
                ($updatedArtist = (new MusicMetadataProvider())->importArtist(
                    $artist,
                ))
            ) {
                $response['artist'] = $updatedArtist;
            }
            $response['artist']->load(['genres']);
            $response['artist']->loadCount(['likes']);
            $response = $this->loadTopTracks($response);
            $response = $this->loadSimilar($response);
            $response = $this->loadProfile($response);
            $response = $this->loadActiveTabData($response);
            $response = $this->loadLatestTrack($response);
            $response = $this->loadLatestAlbum($response);
            $response = $this->loadPrimaryUser($response);
        } elseif ($loader === 'editArtistPage') {
            $response['artist']->load(['genres']);
            $response = $this->loadEditPageAlbums($response);
            $response = $this->loadProfile($response);
        }

        $artist = $response['artist'];

        if ($artist->relationLoaded('latestTrack') && $artist->latestTrack) {
            $response['latest_track'] = (new TrackLoader())->toApiResource(
                $artist->latestTrack,
            );
        }

        if ($artist->relationLoaded('latestAlbum') && $artist->latestAlbum) {
            $response['latest_album'] = (new AlbumLoader())->toApiResource(
                $artist->latestAlbum,
            );
        }

        $response['artist'] = $this->toApiResource(
            $artist,
            $loader,
        );
        return $response;
    }

    public function toApiResource(
        Artist $artist,
        string|null $loader = 'artist',
    ): array {
        $resource = [
            'id' => $artist->id,
            'name' => $artist->name,
            'image_small' => $artist->image_small,
            'verified' => $artist->verified,
            'disabled' => $artist->disabled,
            'model_type' => $artist->model_type,
            'cover_image' => $artist->cover_image,
            'profile_cover' => $artist->profile_cover,
        ];

        if ($loader !== 'artist') {
            $resource['spotify_id'] = $artist->spotify_id;
            $resource['deezer_id'] = $artist->deezer_id;
            $resource['created_at'] = $artist->updated_at?->toJSON();
            $resource['updated_at'] = $artist->updated_at?->toJSON();
            $resource['views'] = $artist->views;
            $resource['plays'] = $artist->plays;
            $resource['disabled'] = $artist->disabled;
        }

        if ($artist->relationLoaded('genres')) {
            $resource['genres'] = $artist->genres
                ->map(
                    fn(Genre $genre) => (new GenreToApiResource())->execute(
                        $genre,
                    ),
                )
                ->toArray();
        }

        if ($artist->relationLoaded('topTracks')) {
            $resource['top_tracks'] = $artist->topTracks
                ->map(
                    fn(Track $track) => (new TrackLoader())->toApiResource(
                        $track,
                    ),
                )
                ->toArray();
        }

        if ($artist->relationLoaded('similar')) {
            $resource['similar'] = $artist->similar
                ->map(fn(Artist $similar) => $this->toApiResource($similar))
                ->toArray();
        }

        if ($artist->relationLoaded('profile')) {
            $resource['profile'] = [
                'city' => $artist->profile?->city,
                'country' => $artist->profile?->country,
                'description' => $artist->profile?->description,
            ];
        }

        if ($artist->relationLoaded('profileImages')) {
            $resource['profile_images'] = $artist->profileImages
                ->map(
                    fn(ProfileImage $image) => [
                        'url' => $image->url,
                        'id' => $image->id,
                    ],
                )
                ->toArray();
        }

        if ($artist->relationLoaded('links')) {
            $resource['links'] = $artist->links
                ->map(
                    fn(ProfileLink $link) => [
                        'url' => $link->url,
                        'title' => $link->title,
                    ],
                )
                ->toArray();
        }

        if ($artist->likes_count !== null) {
            $resource['likes_count'] = $artist->likes_count;
        }

        if ($artist->albums_count !== null) {
            $resource['albums_count'] = $artist->albums_count;
        }

        if ($artist->followers_count !== null) {
            $resource['followers_count'] = $artist->followers_count;
        }

        if ($artist->relationLoaded('primaryUser') && $artist->primaryUser) {
            $resource['primary_user_id'] = $artist->primaryUser->id;
        }

        return $resource;
    }

    protected function loadActiveTabData(array $response): array
    {
        $tabIds = collect(settings('artistPage.tabs'))
            ->filter(fn($tab) => $tab['active'])
            ->map(fn($tab) => (int) $tab['id'])
            ->values();

        $activeTabName = request('tab');
        $activeTabId = static::$artistPageTabs[$activeTabName] ?? null;
        $activeTabId =
            !$activeTabId || !$tabIds->contains($activeTabId)
                ? $tabIds[0] ?? static::$artistPageTabs['discography']
                : $activeTabId;

        if ($activeTabId === static::$artistPageTabs['tracks']) {
            $response['tracks'] = $this->paginateArtistTracks(
                $response['artist'],
            );
            return $response;
        }

        if ($activeTabId === static::$artistPageTabs['discography']) {
            $response['grouped_albums'] = $this->loadDiscographyTabData(
                $response['artist'],
            );
            $response['albums'] = $this->paginateArtistAlbums(
                $response['artist'],
                viewMode: $response['selectedAlbumViewMode'],
            );
            return $response;
        }

        if ($activeTabId === static::$artistPageTabs['albums']) {
            $response['albums'] = $this->paginateArtistAlbums(
                $response['artist'],
                viewMode: 'list',
                perPage: 15,
            );
            return $response;
        }

        if ($activeTabId === static::$artistPageTabs['followers']) {
            $response['followers'] = $this->loadArtistFollowers(
                $response['artist'],
            );
            return $response;
        }

        return $response;
    }

    protected function loadTopTracks(array $response)
    {
        $response['top_tracks'] = $response['artist']
            ->tracks()
            ->orderByPopularity('desc')
            ->with(['album.artists', 'artists'])
            ->limit(20)
            ->get()
            ->map(
                fn(Track $track) => (new TrackLoader())->toApiResource($track),
            )
            ->toArray();
        return $response;
    }

    protected function loadProfile(array $response): array
    {
        $response['artist']->load(['profile', 'profileImages', 'links']);
        return $response;
    }

    protected function loadEditPageAlbums(array $response): array
    {
        $response['albums'] = (new PaginateAlbums())->asApiResponse(
            [
                'perPage' => 50,
                'paginate' => 'simple',
                'page' => 1,
            ],
            $response['artist']->albums(),
            includeScheduled: true,
            loader: 'editArtistPage',
        );
        return $response;
    }

    protected function loadSimilar(array $response): array
    {
        $response['artist']->load('similar');

        if ($response['artist']->similar->isEmpty()) {
            $similar = (new GetSimilarArtists())->execute($response['artist']);
            $response['artist']->setRelation('similar', $similar);
        }

        return $response;
    }

    protected function loadDiscographyTabData(Artist $artist): array
    {
        $visiblePerGroup = 10;
        $prefix = DB::getTablePrefix();

        $rankedAlbums = $artist
            ->albums()
            ->select("{$prefix}albums.*")
            ->selectRaw(
                "row_number() over (
                    partition by {$prefix}albums.record_type
                    order by {$prefix}albums.release_date desc, {$prefix}albums.id desc
                ) as type_rank",
            )
            ->when(
                !(new MusicMetadataProvider())->getProvider(),
                fn($query) => $query->releasedOnly(),
            );

        $albums = Album::query()
            ->fromSub($rankedAlbums->toBase(), 'ranked_albums')
            ->where('ranked_albums.type_rank', '<=', $visiblePerGroup + 1)
            ->orderByRecordType('ranked_albums')
            ->orderBy('ranked_albums.type_rank')
            ->get();

        $grouped = $albums
            ->load('artists')
            ->groupBy('record_type')
            ->map(
                fn($albums) => [
                    'data' => $albums
                        ->take($visiblePerGroup)
                        ->map(
                            fn($album) => (new AlbumLoader())->toApiResource(
                                $album,
                            ),
                        )
                        ->all(),
                    'hasMore' => $albums->count() > $visiblePerGroup,
                ],
            )
            ->filter(fn($item) => !empty($item['data']))
            ->all();

        return $grouped;
    }

    public function paginateArtistAlbums(
        Artist $artist,
        string $viewMode = 'grid',
        int|null $perPage = null,
        bool $includeScheduled = false,
        string|null $loader = null,
        int|null $page = 1,
    ): array {
        $finalPerPage = $perPage ?? ($viewMode === 'list' ? 5 : 25);
        return (new PaginateAlbums())->asApiResponse(
            [
                'perPage' => $finalPerPage,
                'paginate' => 'simple',
                'page' => $page,
            ],
            $artist->albums(),
            includeScheduled: $includeScheduled,
            includeTracks: $viewMode === 'list',
            loader: $loader,
        );
    }

    public function paginateArtistTracks(
        Artist $artist,
        int|null $page = 1,
    ): array {
        return (new PaginateTracks())->asApiResponse(
            [
                'perPage' => request('perPage', 20),
                'order' => 'popularity',
                'paginate' => 'simple',
                'page' => $page,
            ],
            $artist->tracks(),
        );
    }

    public function loadArtistFollowers(Artist $artist)
    {
        return $artist
            ->followers()
            ->with(['subscriptions'])
            ->withCount(['followers'])
            ->simplePaginate(25)
            ->through(
                fn($user) => (new UserProfileLoader())->toApiResource($user),
            );
    }

    protected function loadLatestTrack(array $response): array
    {
        $prefix = DB::getTablePrefix();

        $latestTrack = $response['artist']
            ->tracks()
            ->select("{$prefix}tracks.*")
            ->leftJoin('albums', 'albums.id', '=', 'tracks.album_id')
            ->orderByRaw(
                "coalesce({$prefix}albums.release_date, {$prefix}tracks.created_at) desc",
            )
            ->orderBy('tracks.id', 'desc')
            ->with(['album.artists', 'artists'])
            ->first();

        if ($latestTrack) {
            $response['artist']->setRelation('latestTrack', $latestTrack);
        }

        return $response;
    }

    protected function loadLatestAlbum(array $response): array
    {
        $latestAlbum = $response['artist']
            ->albums()
            ->releasedOnly()
            ->orderBy('albums.release_date', 'desc')
            ->orderBy('albums.id', 'desc')
            ->with(['artists'])
            ->first();

        if ($latestAlbum) {
            $response['artist']->setRelation('latestAlbum', $latestAlbum);
        }

        return $response;
    }

    protected function loadPrimaryUser(array $response): array
    {
        // Load the primary user for the artist (for messaging functionality)
        $primaryUser = User::whereHas('artists', function ($query) use ($response) {
            $query->where('artists.id', $response['artist']->id)
                  ->where('user_artist.role', 'artist');
        })->first();

        if ($primaryUser) {
            $response['artist']->setRelation('primaryUser', $primaryUser);
        }

        return $response;
    }

    public function getFallbackPartialArtist(): array
    {
        return [
            'id' => 0,
            'name' => __('Unknown Artist'),
            'image_small' => null,
            'verified' => false,
            'disabled' => false,
            'model_type' => Artist::MODEL_TYPE,
        ];
    }
}
