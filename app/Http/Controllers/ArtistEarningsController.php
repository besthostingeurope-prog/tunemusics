<?php

namespace App\Http\Controllers;

use App\Models\Artist;
use App\Models\ArtistEarning;
use Carbon\Carbon;
use Common\Core\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ArtistEarningsController extends BaseController
{
    /**
     * Get earnings summary for the authenticated artist.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Get the artist associated with the user
        $artist = $user->primaryArtist();

        if (!$artist) {
            return $this->error('No artist profile found for this user', [], 404);
        }

        $this->authorize('show', $artist);

        $startDate = $request->input('startDate')
            ? Carbon::parse($request->input('startDate'))
            : Carbon::now()->subDays(30);

        $endDate = $request->input('endDate')
            ? Carbon::parse($request->input('endDate'))
            : Carbon::now();

        // Get earnings summary
        // Separate calculations: accrued (payable) vs pending (analytics only)
        $summary = [
            'total_earnings' => [
                'gross' => ArtistEarning::where('artist_id', $artist->id)
                    ->whereIn('status', ['pending', 'accrued'])
                    ->sum('gross_amount'),
                'fee' => ArtistEarning::where('artist_id', $artist->id)
                    ->whereIn('status', ['pending', 'accrued'])
                    ->sum('platform_fee'),
                'net' => ArtistEarning::where('artist_id', $artist->id)
                    ->whereIn('status', ['pending', 'accrued'])
                    ->sum('net_amount'),
            ],
            'pending_earnings' => [
                'gross' => ArtistEarning::where('artist_id', $artist->id)
                    ->where('status', 'pending')
                    ->sum('gross_amount'),
                'fee' => ArtistEarning::where('artist_id', $artist->id)
                    ->where('status', 'pending')
                    ->sum('platform_fee'),
                'net' => ArtistEarning::where('artist_id', $artist->id)
                    ->where('status', 'pending')
                    ->sum('net_amount'),
            ],
            'accrued_earnings' => [
                'gross' => ArtistEarning::where('artist_id', $artist->id)
                    ->where('status', 'accrued')
                    ->sum('gross_amount'),
                'fee' => ArtistEarning::where('artist_id', $artist->id)
                    ->where('status', 'accrued')
                    ->sum('platform_fee'),
                'net' => ArtistEarning::where('artist_id', $artist->id)
                    ->where('status', 'accrued')
                    ->sum('net_amount'),
            ],
            'paid_earnings' => [
                'gross' => ArtistEarning::where('artist_id', $artist->id)
                    ->where('status', 'paid')
                    ->sum('gross_amount'),
                'fee' => ArtistEarning::where('artist_id', $artist->id)
                    ->where('status', 'paid')
                    ->sum('platform_fee'),
                'net' => ArtistEarning::where('artist_id', $artist->id)
                    ->where('status', 'paid')
                    ->sum('net_amount'),
            ],
            'paid_this_month' => [
                'gross' => ArtistEarning::where('artist_id', $artist->id)
                    ->where('status', 'paid')
                    ->whereYear('earning_date', Carbon::now()->year)
                    ->whereMonth('earning_date', Carbon::now()->month)
                    ->sum('gross_amount'),
                'fee' => ArtistEarning::where('artist_id', $artist->id)
                    ->where('status', 'paid')
                    ->whereYear('earning_date', Carbon::now()->year)
                    ->whereMonth('earning_date', Carbon::now()->month)
                    ->sum('platform_fee'),
                'net' => ArtistEarning::where('artist_id', $artist->id)
                    ->where('status', 'paid')
                    ->whereYear('earning_date', Carbon::now()->year)
                    ->whereMonth('earning_date', Carbon::now()->month)
                    ->sum('net_amount'),
            ],
        ];

        // Get daily earnings in date range
        $dailyEarnings = ArtistEarning::where('artist_id', $artist->id)
            ->whereBetween('earning_date', [$startDate, $endDate])
            ->orderBy('earning_date', 'desc')
            ->get();

        // Get monthly breakdown - group by period and status
        // Show all earnings (pending, accrued, paid, requested, rejected) grouped by period
        // Also include earnings without periods (daily tracking) grouped by month
        $monthlyEarningsRaw = ArtistEarning::where('artist_id', $artist->id)
            ->select(
                'period',
                'status',
                DB::raw('SUM(gross_amount) as gross_amount'),
                DB::raw('SUM(platform_fee) as platform_fee'),
                DB::raw('SUM(net_amount) as net_amount'),
                DB::raw('MAX(followers_count) as followers_count'),
                DB::raw('YEAR(earning_date) as year'),
                DB::raw('MONTH(earning_date) as month'),
                DB::raw('COALESCE(period, CONCAT(YEAR(earning_date), "-", LPAD(MONTH(earning_date), 2, "0"))) as sort_key'),
            )
            ->groupBy('period', 'status', DB::raw('YEAR(earning_date)'), DB::raw('MONTH(earning_date)'), DB::raw('COALESCE(period, CONCAT(YEAR(earning_date), "-", LPAD(MONTH(earning_date), 2, "0")))'))
            ->orderBy('sort_key', 'desc')
            ->get();

        // Transform to include year/month for frontend compatibility
        $monthlyEarnings = $monthlyEarningsRaw->map(function ($earning) {
            $year = (int) $earning->year;
            $month = (int) $earning->month;

            // If period exists and is valid, use it; otherwise generate from date
            if ($earning->period) {
                $periodParts = explode('-', $earning->period);
                if (count($periodParts) === 2) {
                    return [
                        'period' => $earning->period,
                        'year' => (int) $periodParts[0],
                        'month' => (int) $periodParts[1],
                        'gross_amount' => (int) $earning->gross_amount,
                        'platform_fee' => (int) $earning->platform_fee,
                        'net_amount' => (int) $earning->net_amount,
                        'followers_count' => (int) $earning->followers_count,
                        'avg_followers' => (int) $earning->followers_count,
                        'status' => $earning->status,
                    ];
                }
            }

            // No period, generate from date
            return [
                'period' => null,
                'year' => $year,
                'month' => $month,
                'gross_amount' => (int) $earning->gross_amount,
                'platform_fee' => (int) $earning->platform_fee,
                'net_amount' => (int) $earning->net_amount,
                'followers_count' => (int) $earning->followers_count,
                'avg_followers' => (int) $earning->followers_count,
                'status' => $earning->status,
            ];
        })->filter();

        // Calculate estimated earnings for PREVIOUS month (will accrue on 5th of current month)
        // Example: If today is Feb 4, show estimate for January (accrues Feb 5)
        $now = Carbon::now();
        $currentPeriod = $now->format('Y-m');
        $previousMonth = $now->copy()->subMonth();
        $previousPeriod = $previousMonth->format('Y-m');
        $previousPeriodEndDate = $previousMonth->copy()->endOfMonth();

        // Get current followers count
        $currentFollowers = $artist->likes()->count();

        // Check if previous period already has accrued earnings
        $previousPeriodAccrued = ArtistEarning::where('artist_id', $artist->id)
            ->where('period', $previousPeriod)
            ->where('status', 'accrued')
            ->exists();

        $estimatedEarnings = null;

        // Show estimate for previous month if:
        // 1. Previous period hasn't been accrued yet
        // 2. We're before the 5th of current month (accrual hasn't happened yet)
        // 3. User had followers at the end of previous month
        if (!$previousPeriodAccrued && $now->day < 5) {
            $followersAtPreviousPeriodEnd = DB::table('likes')
                ->where('likeable_id', $artist->id)
                ->where('likeable_type', Artist::MODEL_TYPE)
                ->where('created_at', '<=', $previousPeriodEndDate->endOfDay())
                ->count();

            if ($followersAtPreviousPeriodEnd > 0) {
                $estimated = ArtistEarning::calculateEarnings($followersAtPreviousPeriodEnd);
                $estimatedEarnings = [
                    'period' => $previousPeriod,
                    'followers_count' => $followersAtPreviousPeriodEnd,
                    'gross_amount' => $estimated['gross_amount'],
                    'platform_fee' => $estimated['platform_fee'],
                    'net_amount' => $estimated['net_amount'],
                    'accrual_date' => $now->copy()->day(5)->format('Y-m-d'),
                ];
            }
        }

        // If no previous month estimate, show current month estimate (for new users)
        // This shows what they'll earn for the current month (accrues next month on 5th)
        if (!$estimatedEarnings && $currentFollowers > 0) {
            $currentPeriodEndDate = $now->copy()->endOfMonth();
            $followersAtCurrentPeriodEnd = DB::table('likes')
                ->where('likeable_id', $artist->id)
                ->where('likeable_type', Artist::MODEL_TYPE)
                ->where('created_at', '<=', $currentPeriodEndDate->endOfDay())
                ->count();

            if ($followersAtCurrentPeriodEnd > 0) {
                $estimated = ArtistEarning::calculateEarnings($followersAtCurrentPeriodEnd);
                $nextMonth = $now->copy()->addMonth();
                $estimatedEarnings = [
                    'period' => $currentPeriod,
                    'followers_count' => $followersAtCurrentPeriodEnd,
                    'gross_amount' => $estimated['gross_amount'],
                    'platform_fee' => $estimated['platform_fee'],
                    'net_amount' => $estimated['net_amount'],
                    'accrual_date' => $nextMonth->day(5)->format('Y-m-d'),
                ];
            }
        }

        return $this->success([
            'summary' => $summary,
            'daily_earnings' => $dailyEarnings,
            'monthly_earnings' => $monthlyEarnings,
            'current_followers' => $currentFollowers,
            'estimated_earnings' => $estimatedEarnings,
        ]);
    }
}
