<?php

namespace App\Http\Controllers;

use App\Models\Artist;
use App\Models\Genre;
use App\Models\Track;
use App\Services\Artists\ArtistLoader;
use App\Services\Genres\GenreToApiResource;
use App\Services\Tracks\TrackLoader;
use Common\Core\BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RadioLikesController extends BaseController
{
    protected array $validTypes = ['artist', 'track', 'genre'];

    public function store(string $type, int $id)
    {
        $this->ensureValidType($type);
        $this->findModel($type, $id);

        $exists = DB::table('likes')
            ->where('user_id', Auth::id())
            ->where('likeable_type', "{$type}_radio")
            ->where('likeable_id', $id)
            ->exists();

        if (!$exists) {
            DB::table('likes')->insert([
                'user_id' => Auth::id(),
                'likeable_type' => "{$type}_radio",
                'likeable_id' => $id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $this->success();
    }

    public function destroy(string $type, int $id)
    {
        $this->ensureValidType($type);

        DB::table('likes')
            ->where('user_id', Auth::id())
            ->where('likeable_type', "{$type}_radio")
            ->where('likeable_id', $id)
            ->delete();

        return $this->success();
    }

    public function index()
    {
        $likes = DB::table('likes')
            ->where('user_id', Auth::id())
            ->whereIn('likeable_type', [
                'artist_radio',
                'track_radio',
                'genre_radio',
            ])
            ->orderByDesc('created_at')
            ->get();

        $radios = $likes
            ->map(function ($like) {
                $type = str_replace('_radio', '', $like->likeable_type);
                try {
                    $model = $this->findModel($type, $like->likeable_id);
                } catch (\Throwable $e) {
                    return null;
                }

                $seed = match ($type) {
                    'artist' => (new ArtistLoader())->toApiResource($model),
                    'genre' => (new GenreToApiResource())->execute($model),
                    'track' => (new TrackLoader())->toApiResource($model),
                };

                return [
                    'type' => $type,
                    'seed' => $seed,
                ];
            })
            ->filter()
            ->values();

        return $this->success(['radios' => $radios]);
    }

    protected function ensureValidType(string $type): void
    {
        abort_unless(in_array($type, $this->validTypes), 404);
    }

    protected function findModel(string $type, int $id)
    {
        return match ($type) {
            'artist' => Artist::findOrFail($id),
            'genre' => Genre::findOrFail($id),
            'track' => Track::with('album.artists', 'artists')->findOrFail(
                $id,
            ),
        };
    }
}
