<?php

namespace App\Http\Controllers\Search;

use App\Models\Artist;
use Common\Billing\Subscription;
use Common\Core\BaseController;
use Illuminate\Support\Facades\Auth;

class ArtistSearchSuggestionsController extends BaseController
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function show($modelId)
    {
        $this->authorize('index', Artist::class);

        if ($modelId === 'CURRENT_USER') {
            $model = $this->getCurrentUserArtist();
        } else {
            $model = Artist::findOrFail($modelId)->toNormalizedArray();
        }

        return $this->success(['model' => $model]);
    }

    public function index()
    {
        $this->authorize('index', Artist::class);

        $limit = request('limit', 10);
        $query = request('query');
        $user = Auth::user();

        $shouldListAll =
            $user?->hasPermission('music.update') ||
            $user?->getRestrictionValue('music.create', 'artist_selection') ||
            request()->has('listAll');

        if ($shouldListAll) {
            $builder = Artist::query();
        } else {
            if (!$user) {
                return $this->success(['results' => []]);
            }

            // For regular users, only show verified artists with active Pro/VIP subscriptions
            $hasActiveSubscription = Subscription::where('user_id', $user->id)
                ->where(function ($q) {
                    $q->whereNull('ends_at')
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

            if (!$hasActiveSubscription) {
                return $this->success(['results' => []]);
            }

            $builder = $user->artists()->where('verified', true);
        }

        $artists = $builder
            ->where('name', 'like', $query . '%')
            ->limit($limit)
            ->get();

        $alreadyHasCurrentUser = $artists->first(function (Artist $artist) {
            return $artist->pivot && $artist->pivot->user_id === Auth::id();
        });

        $artists = $artists->map(
            fn(Artist $artist) => $artist->toNormalizedArray(),
        );

        if (!$alreadyHasCurrentUser && !request()->has('excludeSelf')) {
            $artists->prepend((object) $this->getCurrentUserArtist());
        }

        return $this->success(['results' => $artists]);
    }

    private function getCurrentUserArtist(): array
    {
        return [
            'id' => 'CURRENT_USER',
            'name' => Auth::user()->name,
            'image' => Auth::user()->image,
            'model_type' => Artist::MODEL_TYPE,
        ];
    }
}
