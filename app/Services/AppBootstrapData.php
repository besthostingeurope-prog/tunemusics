<?php namespace App\Services;

use App\Models\Artist;
use App\Models\BackstageRequest;
use App\Models\Playlist;
use App\Models\User;
use App\Services\Playlists\PlaylistLoader;
use Common\Billing\Subscription;
use Common\Core\Bootstrap\BaseBootstrapData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AppBootstrapData extends BaseBootstrapData
{
    public function init(): self
    {
        parent::init();

        if (isset($this->data['user'])) {
            $this->getUserLikes();
            $this->loadUserPlaylists();
            $this->loadManagedArtists();
            $this->loadUserReposts();
            $this->loadPendingBackstageRequestsCount();
        }

        $this->data['settings']['spotify_is_setup'] =
            config('services.spotify.id') && config('services.spotify.secret');
        $this->data['settings']['spotify_use_deprecated_api'] = config(
            'services.spotify.use_deprecated_api',
        );

        return $this;
    }

    /**
     * Get ids of all tracks in current user's library.
     */
    private function getUserLikes(): void
    {
        $this->data['likes'] = DB::table('likes')
            ->where('user_id', $this->data['user']['id'])
            ->get(['likeable_id', 'likeable_type'])
            ->groupBy(fn($likeable) => $likeable->likeable_type)
            ->map(function (Collection $likeableGroup) {
                return $likeableGroup->mapWithKeys(function ($likeable) {
                    return [$likeable->likeable_id => true];
                });
            });
    }

    private function loadUserPlaylists(): void
    {
        $this->data['playlists'] = $this->data['user']
            ->playlists()
            ->with(['editors'])
            ->limit(30)
            ->orderBy('playlists.updated_at', 'desc')
            ->get()
            ->map(
                fn(Playlist $playlist) => (new PlaylistLoader())->toApiResource(
                    $playlist,
                ),
            )
            ->toArray();
    }

    private function loadManagedArtists(): void
    {
        $user = $this->data['user'];

        // Check if user has active Pro/VIP subscription
        $hasActiveSubscription = Subscription::where('user_id', $user->id)
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->whereHas('product', function ($productQuery) {
                $productQuery->where(function ($pq) {
                    $pq->whereRaw('LOWER(name) LIKE ?', ['%pro%'])
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%vip%'])
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%artist%']);
                })
                ->where('free', false);
            })
            ->exists();

        $query = $user->artists();

        // Only filter by subscription if user doesn't have admin permissions
        if (!$user->hasPermission('music.update') && !$hasActiveSubscription) {
            // Return empty collection if no active subscription
            $this->data['user']['artists'] = collect([]);
            return;
        }

        $this->data['user']['artists'] = $query
            ->get(['artists.id', 'name', 'image_small', 'verified'])
            ->map(function (Artist $artist) {
                $normalizedModel = $artist->toNormalizedArray();
                $normalizedModel['role'] = $artist->pivot->role;
                $normalizedModel['verified'] = $artist->verified;
                return $normalizedModel;
            });
    }

    private function loadPendingBackstageRequestsCount(): void
    {
        if ($this->data['user']->hasPermission('backstageRequests.update')) {
            $this->data['settings']['pending_backstage_requests_count'] =
                BackstageRequest::where('status', 'pending')->count();
        }
    }

    private function loadUserReposts(): void
    {
        $this->data['reposts'] = DB::table('reposts')
            ->where('user_id', $this->data['user']['id'])
            ->get(['repostable_id', 'repostable_type'])
            ->groupBy(fn($item) => $item->repostable_type)
            ->map(function (Collection $likeableGroup) {
                return $likeableGroup->mapWithKeys(
                    fn($item) => [$item->repostable_id => true],
                );
            });
    }
}
