<?php

namespace App\Http\Controllers\Artist;

use App\Models\Artist;
use Common\Core\BaseController;
use Illuminate\Support\Facades\Auth;

class ArtistFollowController extends BaseController
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function follow(Artist $artist)
    {
        $this->authorize('follow', $artist);

        $user = Auth::user();

        // Check if already following
        if (!$user->likedArtists()->where('likeable_id', $artist->id)->exists()) {
            $user->likedArtists()->attach($artist->id);
        }

        return $this->success(['message' => __('Artist followed successfully')]);
    }

    public function unfollow(Artist $artist)
    {
        $user = Auth::user();

        $user->likedArtists()->detach($artist->id);

        return $this->success(['message' => __('Artist unfollowed successfully')]);
    }
}
