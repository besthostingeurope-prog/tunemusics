<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

use App\Http\Controllers\AlbumController;
use App\Http\Controllers\Artist\ArtistFollowController;
use App\Http\Controllers\Artist\ArtistFollowersController;
use App\Http\Controllers\Artist\ArtistTracksController;
use App\Http\Controllers\ArtistAlbumsController;
use App\Http\Controllers\ArtistController;
use App\Http\Controllers\ArtistEarningsController;
use App\Http\Controllers\ArtistPayoutController;
use App\Http\Controllers\ArtistPayoutMethodController;
use App\Http\Controllers\Admin\AdminPayoutController;
use App\Http\Controllers\Admin\ArtistEarningsController as AdminArtistEarningsController;
use App\Http\Controllers\Admin\PayoutStatsController;
use App\Http\Controllers\BackstageRequestController;
use App\Http\Controllers\DownloadLocalTrackController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\ImportMediaController;
use App\Http\Controllers\InsightsReportController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\RevenueReportController;
use App\Http\Controllers\LyricsController;
use App\Http\Controllers\MinutesLimitController;
use App\Http\Controllers\PlayerTracksController;
use App\Http\Controllers\PlaylistController;
use App\Http\Controllers\PlaylistTracksController;
use App\Http\Controllers\PlaylistTracksOrderController;
use App\Http\Controllers\RadioController;
use App\Http\Controllers\RadioLikesController;
use App\Http\Controllers\RepostController;
use App\Http\Controllers\Search\AlbumSearchSuggestionsController;
use App\Http\Controllers\Search\ArtistSearchSuggestionsController;
use App\Http\Controllers\Search\SearchController;
use App\Http\Controllers\TagMediaController;
use App\Http\Controllers\Track\TrackLikeController;
use App\Http\Controllers\TrackController;
use App\Http\Controllers\TrackFileMetadataController;
use App\Http\Controllers\TrackPlaysController;
use App\Http\Controllers\UserLibrary\UserLibraryAlbumsController;
use App\Http\Controllers\UserLibrary\UserLibraryArtistsController;
use App\Http\Controllers\UserLibrary\UserLibraryTracksController;
use App\Http\Controllers\UserProfile\UserPlaylistsController;
use App\Http\Controllers\UserProfile\UserProfileController;
use App\Http\Controllers\WaveController;
use App\Http\Controllers\YoutubeLogController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\Admin\AdminConversationController;
use App\Http\Controllers\Admin\ArtistEmailController;
use App\Http\Controllers\Admin\LiveListenersController;
use App\Http\Controllers\OneTapAuthController;
use App\Http\Controllers\ConcertEventsController;
use App\Http\Controllers\PageSectionController;
use Common\Auth\Controllers\FollowedUsersController;
use Common\Auth\Controllers\FollowersController;
use Common\Channels\ChannelContentOrderController;
use Common\Channels\ChannelController;
use Common\Channels\ChannelItemController;
use Illuminate\Support\Facades\Route;


// Route::group(['prefix' => 'v1', 'middleware' => ['auth:sanctum', 'verified']], function() {
    
    
//     Route::post('users/{user_id}/add-to-library', [UserLibraryTracksController::class, 'addToLibraryUserId']);
//     Route::post('users/{user_id}/remove-from-library', [UserLibraryTracksController::class, 'removeFromLibraryUserId']);
    
    
    
// });



Route::group(['prefix' => 'v1', 'middleware' => ['optionalAuth:sanctum', 'verified']], function() {
    // LANDING
    Route::get('landing-page-data', LandingPageController::class);

    // SEARCH
    Route::get('search/audio/{trackId}/{artistName}/{trackName}', [SearchController::class, 'searchAudio']);
    Route::get('search', [SearchController::class, 'index']);
    Route::get('search/model/{modelType}', [SearchController::class, 'searchSingleModelType']);
    Route::get('search/suggestions/artist', [ArtistSearchSuggestionsController::class, 'index']);
    Route::get('search/suggestions/artist/{id}', [ArtistSearchSuggestionsController::class, 'show']);
    Route::get('search/suggestions/album', [AlbumSearchSuggestionsController::class, 'index']);
    Route::get('search/suggestions/album/{id}', [AlbumSearchSuggestionsController::class, 'show']);

    // CHANNELS
    Route::post('channel/{channel}/update-content', [ChannelController::class, 'updateContent']);
    Route::get('channel/search-for-addable-content', [ChannelController::class, 'searchForAddableContent']);
    Route::post('channel/apply-preset', [ChannelController::class, 'applyPreset']);
    Route::post('channel/{channel}/add',  [ChannelItemController::class, 'add']);
    Route::post('channel/{channel}/remove', [ChannelItemController::class, 'remove']);
    Route::post('channel/{id}/reorder-content', [ChannelContentOrderController::class, 'changeOrder']);
    Route::get('channel', [ChannelController::class, 'index']);
    Route::get('channel/{channel}', [ChannelController::class, 'show']);
    Route::post('channel', [ChannelController::class, 'store']);
    Route::put('channel/{channel}', [ChannelController::class, 'update']);
    Route::delete('channel/{ids}', [ChannelController::class, 'destroy']);

    // PLAYLISTS
    Route::get('playlists/{id}', [PlaylistController::class, 'show']);
    Route::get('playlists', [PlaylistController::class, 'index']);
    Route::put('playlists/{playlist}', [PlaylistController::class, 'update']);
    Route::post('playlists', [PlaylistController::class, 'store']);
    Route::delete('playlists/{ids}', [PlaylistController::class, 'destroy']);
    Route::post('playlists/{id}/follow', [UserPlaylistsController::class, 'follow']);
    Route::post('playlists/{id}/unfollow', [UserPlaylistsController::class, 'unfollow']);
    Route::get('playlists/{id}/tracks', [PlaylistTracksController::class, 'index']);
    Route::post('playlists/{id}/tracks/add', [PlaylistTracksController::class, 'add']);
    Route::post('playlists/{id}/tracks/remove', [PlaylistTracksController::class, 'remove']);
    Route::post('playlists/{playlist}/tracks/order', [PlaylistTracksOrderController::class, 'change']);

    // ARTISTS
    Route::get('artists', [ArtistController::class, 'index']);
    Route::post('artists', [ArtistController::class, 'store']);
    Route::get('artists/earnings', [ArtistEarningsController::class, 'index']);

    // ARTIST PAYOUTS (must be before artists/{artist} route)
    Route::get('artists/payout-methods', [ArtistPayoutMethodController::class, 'index']);
    Route::post('artists/payout-methods', [ArtistPayoutMethodController::class, 'store']);
    Route::put('artists/payout-methods/{payoutMethod}', [ArtistPayoutMethodController::class, 'update']);
    Route::delete('artists/payout-methods/{payoutMethod}', [ArtistPayoutMethodController::class, 'destroy']);
    Route::get('artists/payouts', [ArtistPayoutController::class, 'index']);
    Route::post('artists/payouts', [ArtistPayoutController::class, 'store']);
    Route::post('artists/payouts/{payout}/cancel', [ArtistPayoutController::class, 'cancel']);

    Route::put('artists/{artist}', [ArtistController::class, 'update'])->where('artist', '[0-9]+');
    Route::get('artists/{artist}', [ArtistController::class, 'show'])->where('artist', '[0-9]+');
    Route::delete('artists/{ids}', [ArtistController::class, 'destroy']);
    Route::get('artists/{artist}/tracks', [ArtistTracksController::class, 'index'])->where('artist', '[0-9]+');
    Route::get('artists/{artist}/albums', [ArtistAlbumsController::class, 'index'])->where('artist', '[0-9]+');
    Route::get('artists/{artist}/followers', [ArtistFollowersController::class, 'index'])->where('artist', '[0-9]+');
    Route::post('artists/{artist}/follow', [ArtistFollowController::class, 'follow'])->where('artist', '[0-9]+');
    Route::post('artists/{artist}/unfollow', [ArtistFollowController::class, 'unfollow'])->where('artist', '[0-9]+');

    // ALBUMS
    Route::get('albums', [AlbumController::class, 'index']);
    Route::get('albums/{album}', [AlbumController::class, 'show']);
    Route::post('albums', [AlbumController::class, 'store']);
    Route::put('albums/{album}', [AlbumController::class, 'update']);
    Route::delete('albums/{ids}', [AlbumController::class, 'destroy']);

    // TRACKS
    Route::get('tracks/{track}/wave', [WaveController::class, 'show']);
    Route::get('tracks', [TrackController::class, 'index']);
    Route::get('tracks/{id}/download', [DownloadLocalTrackController::class, 'download']);
    Route::post('tracks', [TrackController::class, 'store']);
    Route::put('tracks/{id}', [TrackController::class, 'update']);
    Route::get('tracks/{track}', [TrackController::class, 'show']);
    Route::delete('tracks/{ids}', [TrackController::class, 'destroy']);
    Route::post('tracks/{fileEntry}/extract-metadata', [TrackFileMetadataController::class, 'extract']);
    Route::post('tracks/{track}/like', [TrackLikeController::class, 'like']);
    Route::post('tracks/{track}/unlike', [TrackLikeController::class, 'unlike']);

    // TRACK PLAYS
    Route::post('player/tracks', [PlayerTracksController::class, 'index']);
    Route::get('tracks/plays/{userId}', [TrackPlaysController::class, 'index']);
    Route::post('tracks/plays/{track}/log', [TrackPlaysController::class, 'create']);

    // LYRICS
    Route::get('lyrics', [LyricsController::class, 'index']);
    Route::post('lyrics', [LyricsController::class, 'store']);
    Route::delete('lyrics/{ids}', [LyricsController::class, 'destroy']);
    Route::get('tracks/{id}/lyrics', [LyricsController::class, 'show']);
    Route::put('lyrics/{id}', [LyricsController::class, 'update']);

    // RADIO
    Route::get('radio/{type}/{id}', [RadioController::class, 'getRecommendations']);

    // CONCERT EVENTS (Ticketmaster)
    Route::get('concert-events/search', [ConcertEventsController::class, 'search']);
    Route::get('concert-events/artist/{name}', [ConcertEventsController::class, 'forArtist']);

    // PRESS / BLOG PAGE SECTIONS
    Route::get('pages-section/{section}', [PageSectionController::class, 'index']);
    Route::get('pages-section/{section}/{slug}', [PageSectionController::class, 'show']);

    // GOOGLE ONE TAP SIGN-IN
    Route::get('auth/one-tap/config', [OneTapAuthController::class, 'config']);
    Route::post('auth/one-tap', [OneTapAuthController::class, 'login'])->middleware('throttle:20,1');

    // RADIO LIKES
    Route::group(['prefix' => 'radio', 'middleware' => 'auth:sanctum'], function() {
        Route::get('/likes', [RadioLikesController::class, 'index']);
        Route::post('/{type}/{id}/like', [RadioLikesController::class, 'store']);
        Route::delete('/{type}/{id}/like', [RadioLikesController::class, 'destroy']);
    });

    // TAGS
    Route::get('tags/{tagName}/tracks', [TagMediaController::class, 'tracks']);
    Route::get('tags/{tagName}/albums', [TagMediaController::class, 'albums']);

    // GENRES
    Route::get('genres', [GenreController::class, 'index']);
    Route::post('genres', [GenreController::class, 'store']);
    Route::put('genres/{id}', [GenreController::class, 'update']);
    Route::delete('genres/{ids}', [GenreController::class, 'destroy']);

    // USER PROFILE AND LIBRARY
    Route::get('user-profile/{user}', [UserProfileController::class, 'show'])->withoutMiddleware('verified');
    Route::get('users/{user}/minutes-left', MinutesLimitController::class);
    Route::get('users/{user}/liked-tracks', [UserLibraryTracksController::class, 'index']);
    Route::get('users/{user}/liked-albums', [UserLibraryAlbumsController::class, 'index']);
    Route::get('users/{user}/liked-artists', [UserLibraryArtistsController::class, 'index']);
    Route::get('users/{user}/playlists', [UserPlaylistsController::class, 'index']);
    Route::get('users/{user}/followers', [FollowersController::class, 'index']);
    Route::get('users/{user}/followed-users', [FollowedUsersController::class, 'index']);
    Route::put('users/profile/update', [UserProfileController::class, 'update']);
    
    Route::post('users/me/add-to-library', [UserLibraryTracksController::class, 'addToLibrary']);
    Route::post('users/me/remove-from-library', [UserLibraryTracksController::class, 'removeFromLibrary']);
    
    
    // USER FOLLOWERS
    Route::post('users/{id}/follow', [FollowersController::class, 'follow']);
    Route::post('users/{id}/unfollow', [FollowersController::class, 'unfollow']);

    // REPOSTS
    Route::get('users/{user}/reposts', [RepostController::class, 'index']);
    Route::post('reposts/toggle', [RepostController::class, 'toggle']);

    // BACKSTAGE REQUESTS
    Route::post('backstage-request/{backstageRequest}/approve', [BackstageRequestController::class, 'approve']);
    Route::post('backstage-request/{backstageRequest}/deny', [BackstageRequestController::class, 'deny']);
    Route::apiResource('backstage-request', BackstageRequestController::class);

    // REPORTS
    Route::get('reports/insights', InsightsReportController::class);
    Route::get('reports/revenue', RevenueReportController::class);

    // ADMIN ARTIST EARNINGS
    Route::get('admin/artist-earnings', [AdminArtistEarningsController::class, 'index']);
    Route::post('admin/artist-earnings/calculate', [AdminArtistEarningsController::class, 'calculate']);
    Route::get('admin/payout-stats', [PayoutStatsController::class, 'index']);

    // ADMIN PAYOUTS
    Route::get('admin/payouts', [AdminPayoutController::class, 'index']);
    Route::post('admin/payouts/{payout}/approve', [AdminPayoutController::class, 'approve']);
    Route::post('admin/payouts/{payout}/reject', [AdminPayoutController::class, 'reject']);
    Route::post('admin/payouts/{payout}/processing', [AdminPayoutController::class, 'markAsProcessing']);
    Route::post('admin/payouts/{payout}/complete', [AdminPayoutController::class, 'markAsCompleted']);
    Route::post('admin/payouts/{payout}/failed', [AdminPayoutController::class, 'markAsFailed']);

    // YOUTUBE
    Route::post('youtube/log-client-error', [YoutubeLogController::class, 'store']);

    // IMPORT
    Route::post('import-media/single-item', [ImportMediaController::class, 'import']);

    // CHAT - User endpoints (read operations)
    Route::group(['prefix' => 'conversations', 'middleware' => ['auth:sanctum', 'throttle:60,1']], function() {
        Route::get('/search-users', [ConversationController::class, 'searchUsers']);
        Route::get('/', [ConversationController::class, 'index']);
        Route::get('/{conversation}', [ConversationController::class, 'show']);
        Route::patch('/{conversation}/read', [ConversationController::class, 'markAsRead']);
        Route::patch('/{conversation}/archive', [ConversationController::class, 'archive']);
        Route::patch('/{conversation}/mute', [ConversationController::class, 'mute']);
    });

    // CHAT - User endpoints (write operations)
    Route::group(['prefix' => 'conversations', 'middleware' => ['auth:sanctum', 'throttle:30,1']], function() {
        Route::post('/', [ConversationController::class, 'store']);
        Route::post('/{conversation}/messages', [MessageController::class, 'store']);
    });

    // CHAT - Message operations
    Route::group(['middleware' => ['auth:sanctum', 'throttle:30,1']], function() {
        Route::patch('/messages/{message}', [MessageController::class, 'update']);
        Route::delete('/messages/{message}', [MessageController::class, 'destroy']);
    });

    // ARTIST EMAILS - Admin
    Route::group(['prefix' => 'admin/artist-emails', 'middleware' => 'auth:sanctum'], function() {
        Route::get('/recipients', [ArtistEmailController::class, 'recipients']);
        Route::get('/history', [ArtistEmailController::class, 'history']);
        Route::post('/preview', [ArtistEmailController::class, 'preview']);
        Route::post('/send', [ArtistEmailController::class, 'send']);
    });

    // LIVE LISTENERS - Admin
    Route::group(['prefix' => 'admin/live-listeners', 'middleware' => 'auth:sanctum'], function() {
        Route::get('/', [LiveListenersController::class, 'index']);
    });

    // CHAT - Admin endpoints
    Route::group(['prefix' => 'admin/conversations', 'middleware' => 'auth:sanctum'], function() {
        Route::get('/', [AdminConversationController::class, 'index']);
        Route::get('/{conversation}', [AdminConversationController::class, 'show']);
        Route::patch('/{conversation}/block', [AdminConversationController::class, 'block']);
    });
});




