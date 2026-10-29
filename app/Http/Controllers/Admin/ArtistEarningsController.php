<?php

namespace App\Http\Controllers\Admin;

use App\Console\Commands\CalculateDailyArtistEarnings;
use App\Models\Artist;
use App\Models\ArtistEarning;
use App\Models\ArtistPayout;
use App\Models\ArtistPayoutMethod;
use Carbon\Carbon;
use Common\Billing\Subscription;
use Common\Core\BaseController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class ArtistEarningsController extends BaseController
{
    public function index()
    {
        // Get ALL verified artists with active Pro/VIP subscriptions
        // Show all artists, even if they have 0 accrued earnings
        $artistIds = Artist::where('artists.verified', true)
            ->join('user_artist', function ($join) {
                $join->on('artists.id', '=', 'user_artist.artist_id')
                    ->where('user_artist.role', '=', 'artist');
            })
            ->join('users', 'user_artist.user_id', '=', 'users.id')
            ->join('subscriptions', function ($join) {
                $join->on('users.id', '=', 'subscriptions.user_id')
                    ->where(function ($q) {
                        $q->whereNull('subscriptions.ends_at')
                            ->orWhere('subscriptions.ends_at', '>', now());
                    });
            })
            ->join('products', 'subscriptions.product_id', '=', 'products.id')
            ->where(function ($query) {
                $query->whereRaw('LOWER(products.name) LIKE ?', ['%pro%'])
                    ->orWhereRaw('LOWER(products.name) LIKE ?', ['%vip%'])
                    ->orWhereRaw('LOWER(products.name) LIKE ?', ['%artist%']);
            })
            ->where('products.free', false)
            ->select('artists.id')
            ->distinct()
            ->pluck('id');

        // Get artist details
        $artistDetails = Artist::whereIn('artists.id', $artistIds)
            ->select('artists.id as artist_id', 'artists.name as artist_name')
            ->get()
            ->keyBy('artist_id');

        // Batch fetch user emails
        $userEmails = DB::table('user_artist')
            ->whereIn('artist_id', $artistIds)
            ->where('role', 'artist')
            ->join('users', 'user_artist.user_id', '=', 'users.id')
            ->select('user_artist.artist_id', 'users.email', 'users.id as user_id')
            ->get()
            ->keyBy('artist_id');

        // Batch fetch product names
        $userIds = $userEmails->pluck('user_id')->unique();
        $products = DB::table('subscriptions')
            ->whereIn('user_id', $userIds)
            ->where(function ($q) {
                $q->whereNull('subscriptions.ends_at')
                    ->orWhere('subscriptions.ends_at', '>', now());
            })
            ->join('products', 'subscriptions.product_id', '=', 'products.id')
            ->where(function ($query) {
                $query->whereRaw('LOWER(products.name) LIKE ?', ['%pro%'])
                    ->orWhereRaw('LOWER(products.name) LIKE ?', ['%vip%'])
                    ->orWhereRaw('LOWER(products.name) LIKE ?', ['%artist%']);
            })
            ->where('products.free', false)
            ->select('subscriptions.user_id', 'products.name as product_name')
            ->get()
            ->groupBy('user_id')
            ->map(function ($userProducts) {
                return $userProducts->first()->product_name;
            });

        // Combine artist data
        $artists = $artistDetails->map(function ($artist) use ($userEmails, $products) {
            $userEmail = $userEmails->get($artist->artist_id);
            $productName = $userEmail ? $products->get($userEmail->user_id) : null;

            return [
                'artist_id' => $artist->artist_id,
                'artist_name' => $artist->artist_name,
                'user_email' => $userEmail->email ?? null,
                'product_name' => $productName,
            ];
        })->values();

        $artistIds = $artists->pluck('artist_id')->unique()->values();

        // Get current followers count for all artists
        // Use Artist model to ensure correct likeable_type format
        $followersCounts = Artist::whereIn('id', $artistIds)
            ->withCount('likes')
            ->get()
            ->mapWithKeys(function ($artist) {
                return [$artist->id => (object)['count' => $artist->likes_count ?? 0]];
            });

        // Get accrued earnings grouped by period
        $accruedEarnings = ArtistEarning::whereIn('artist_id', $artistIds)
            ->where('status', 'accrued')
            ->whereNotNull('period')
            ->select('artist_id', 'period', DB::raw('SUM(gross_amount) as gross'), DB::raw('SUM(net_amount) as net'), DB::raw('MAX(followers_count) as followers_at_period'))
            ->groupBy('artist_id', 'period')
            ->get()
            ->groupBy('artist_id');

        // Get total accrued per artist
        $totalAccrued = ArtistEarning::whereIn('artist_id', $artistIds)
            ->where('status', 'accrued')
            ->select('artist_id', DB::raw('SUM(gross_amount) as gross'), DB::raw('SUM(net_amount) as net'))
            ->groupBy('artist_id')
            ->get()
            ->keyBy('artist_id');

        // Get latest pending earnings (for analytics display - snapshot only, not sum)
        // Pending is just daily tracking snapshots, not cumulative earnings
        $latestPending = ArtistEarning::whereIn('artist_id', $artistIds)
            ->where('status', 'pending')
            ->select('artist_id', 'gross_amount as gross', 'net_amount as net', 'earning_date')
            ->orderBy('earning_date', 'desc')
            ->get()
            ->groupBy('artist_id')
            ->map(function ($earnings) {
                // Get the latest pending record (most recent snapshot)
                return $earnings->first();
            })
            ->keyBy('artist_id');

        // Get payout methods
        $payoutMethods = ArtistPayoutMethod::whereIn('artist_id', $artistIds)
            ->get()
            ->keyBy('artist_id');

        // Get paid today status
        $paidToday = ArtistPayout::whereIn('artist_id', $artistIds)
            ->whereDate('completed_at', Carbon::today())
            ->where('status', 'completed')
            ->pluck('artist_id')
            ->toArray();

        $earnings = $artists->map(function ($artist) use ($followersCounts, $accruedEarnings, $totalAccrued, $latestPending, $payoutMethods, $paidToday) {
            $artistId = $artist['artist_id'];

            $followersData = $followersCounts->get($artistId);
            $currentFollowers = $followersData ? (int) $followersData->count : 0;

            $accruedData = $totalAccrued->get($artistId);
            $gross = $accruedData ? (int) $accruedData->gross : 0;
            $net = $accruedData ? (int) $accruedData->net : 0;

            $pendingData = $latestPending->get($artistId);
            $pendingGross = $pendingData ? (int) $pendingData->gross : 0;
            $pendingNet = $pendingData ? (int) $pendingData->net : 0;

            $periodEarnings = $accruedEarnings->get($artistId) ?? collect();

            $payoutMethod = $payoutMethods->get($artistId);

            return [
                'artist_id' => $artistId,
                'artist_name' => $artist['artist_name'],
                'user_email' => $artist['user_email'],
                'product_name' => $artist['product_name'],
                'followers' => $currentFollowers,
                'per_follower' => 10, // $0.10 in cents
                'gross' => $gross,
                'net' => $net,
                'pending_gross' => $pendingGross,
                'pending_net' => $pendingNet,
                'fee' => 10,
                'has_payout_method' => $payoutMethod !== null,
                'bank_details' => $payoutMethod ? [
                    'type' => $payoutMethod->type,
                    'account_holder_name' => $payoutMethod->account_holder_name,
                    'bank_name' => $payoutMethod->bank_name,
                    'iban' => $payoutMethod->getRawOriginal('iban'),
                    'account_number' => $payoutMethod->getRawOriginal('account_number'),
                    'routing_number' => $payoutMethod->getRawOriginal('routing_number'),
                    'swift_code' => $payoutMethod->swift_code,
                    'country' => $payoutMethod->country,
                    'paypal_email' => $payoutMethod->paypal_email,
                ] : null,
                'status' => $net > 0 ? 'accrued' : ($pendingNet > 0 ? 'pending' : 'none'),
                'paid_today' => in_array($artistId, $paidToday),
                'periods' => $periodEarnings->map(function ($periodEarning) {
                    return [
                        'period' => $periodEarning->period,
                        'gross' => (int) $periodEarning->gross,
                        'net' => (int) $periodEarning->net,
                        'followers_at_period' => (int) $periodEarning->followers_at_period,
                    ];
                })->values()->toArray(),
            ];
        })->values();

        return $this->success(['earnings' => $earnings]);
    }

    public function calculate()
    {
        $period = request('period');
        $date = request('date');

        if ($period) {
            // Validate period format
            if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
                return $this->error('Invalid period format. Expected format: YYYY-MM', [], 422);
            }

            $exitCode = Artisan::call('earnings:calculate-daily', [
                '--period' => $period,
            ]);
            $message = "Monthly accrual calculation completed for period: {$period}";
        } else {
            $date = $date ? Carbon::parse($date) : Carbon::yesterday();
            $exitCode = Artisan::call('earnings:calculate-daily', [
                '--date' => $date->format('Y-m-d'),
            ]);
            $message = "Daily earnings calculation completed for: {$date->format('Y-m-d')}";
        }

        if ($exitCode !== 0) {
            return $this->error('Failed to calculate earnings');
        }

        $output = Artisan::output();

        return $this->success([
            'message' => $message,
            'output' => $output,
        ]);
    }
}
