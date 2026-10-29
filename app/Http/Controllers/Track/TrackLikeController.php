<?php

namespace App\Http\Controllers\Track;

use App\Models\Track;
use Common\Core\BaseController;
use Illuminate\Support\Facades\Auth;

class TrackLikeController extends BaseController
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function like(Track $track)
    {
        $this->authorize('like', $track);

        $user = Auth::user();

        // Check if already liked
        if (!$user->likedTracks()->where('likeable_id', $track->id)->exists()) {
            $user->likedTracks()->attach($track->id);
        }

        return $this->success(['message' => __('Track liked successfully')]);
    }

    public function unlike(Track $track)
    {
        $user = Auth::user();

        $user->likedTracks()->detach($track->id);

        return $this->success(['message' => __('Track unliked successfully')]);
    }
}
