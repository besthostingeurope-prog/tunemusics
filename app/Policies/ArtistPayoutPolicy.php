<?php

namespace App\Policies;

use App\Models\ArtistPayout;
use Common\Auth\BaseUser;
use Illuminate\Auth\Access\HandlesAuthorization;

class ArtistPayoutPolicy
{
    use HandlesAuthorization;

    public function index(BaseUser $user)
    {
        return $user->hasPermission('admin.access');
    }

    public function show(BaseUser $user, ArtistPayout $payout)
    {
        return $user->hasPermission('admin.access');
    }

    public function store(BaseUser $user)
    {
        return $user->hasPermission('admin.access');
    }

    public function update(BaseUser $user, ArtistPayout $payout = null)
    {
        return $user->hasPermission('admin.access');
    }

    public function destroy(BaseUser $user, ArtistPayout $payout)
    {
        return $user->hasPermission('admin.access');
    }
}
