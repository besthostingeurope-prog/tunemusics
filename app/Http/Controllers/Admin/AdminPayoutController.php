<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\ArtistPayout;
use App\Models\ArtistEarning;
use App\Models\ArtistPayoutMethod;
use App\Models\User;
use Carbon\Carbon;
use Common\Core\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminPayoutController extends BaseController
{
    public function index(Request $request)
    {
        $this->authorize('index', ArtistPayout::class);

        $query = ArtistPayout::with(['artist', 'payoutMethod']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        // Search by artist name
        if ($search = $request->get('query')) {
            $query->whereHas('artist', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        // Order by requested date
        $query->orderBy('requested_at', 'desc');

        $payouts = $query->paginate($request->get('per_page', 15));

        // Get all artist IDs for batch processing
        $artistIds = $payouts->getCollection()->pluck('artist_id')->unique();

        // Batch fetch user emails
        $userEmails = DB::table('user_artist')
            ->whereIn('artist_id', $artistIds)
            ->where('role', 'artist')
            ->join('users', 'user_artist.user_id', '=', 'users.id')
            ->select('user_artist.artist_id', 'users.email')
            ->get()
            ->keyBy('artist_id');

        // Batch fetch followers counts
        $followersCounts = DB::table('likes')
            ->whereIn('likeable_id', $artistIds)
            ->where('likeable_type', \App\Models\Artist::MODEL_TYPE)
            ->select('likeable_id as artist_id', DB::raw('COUNT(*) as count'))
            ->groupBy('likeable_id')
            ->get()
            ->keyBy('artist_id');

        // Batch fetch total earnings per artist (accrued + paid) for display
        $totalAccruedTotals = ArtistEarning::whereIn('artist_id', $artistIds)
            ->whereIn('status', ['accrued', 'paid'])
            ->select(
                'artist_id',
                DB::raw('SUM(gross_amount) as gross_total'),
                DB::raw('SUM(platform_fee) as fee_total'),
                DB::raw('SUM(net_amount) as net_total')
            )
            ->groupBy('artist_id')
            ->get()
            ->keyBy('artist_id');

        // Batch fetch period earnings for all unique artist+period combinations
        $periodEarningsMap = [];
        $periodGrossMap = [];
        $periodFeeMap = [];
        $uniquePeriodCombos = [];
        foreach ($payouts->getCollection() as $payout) {
            if ($payout->period) {
                $periods = array_filter(explode(',', $payout->period));
                sort($periods); // Sort for consistent key generation
                $key = $payout->artist_id . '|' . implode(',', $periods);
                if (!isset($uniquePeriodCombos[$key])) {
                    $uniquePeriodCombos[$key] = [
                        'artist_id' => $payout->artist_id,
                        'periods' => $periods,
                    ];
                }
            }
        }

        // Fetch period earnings in batch
        foreach ($uniquePeriodCombos as $key => $combo) {
            $totals = ArtistEarning::where('artist_id', $combo['artist_id'])
                ->whereIn('period', $combo['periods'])
                ->whereIn('status', ['accrued', 'paid'])
                ->selectRaw('SUM(gross_amount) as gross_total, SUM(platform_fee) as fee_total, SUM(net_amount) as net_total')
                ->first();
            $periodEarningsMap[$key] = $totals? (int) ($totals->net_total ?? 0) : 0;
            $periodGrossMap[$key] = $totals? (int) ($totals->gross_total ?? 0) : 0;
            $periodFeeMap[$key] = $totals? (int) ($totals->fee_total ?? 0) : 0;
        }

        // Add user email, followers count, and earnings to each payout
        $payouts->getCollection()->transform(function ($payout) use ($userEmails, $followersCounts, $totalAccruedTotals, $periodEarningsMap, $periodGrossMap, $periodFeeMap) {
            $userEmail = $userEmails->get($payout->artist_id);
            if ($userEmail && $payout->artist) {
                $payout->artist->user_email = $userEmail->email;
            }

            // Get current followers count
            $followersData = $followersCounts->get($payout->artist_id);
            $payout->followers_count = $followersData ? (int) $followersData->count : 0;

            // Get total accrued earnings for this artist (gross/fee/net)
            $accruedData = $totalAccruedTotals->get($payout->artist_id);
            $payout->total_accrued_earnings = $accruedData ? (int) ($accruedData->net_total ?? 0) : 0;
            $payout->total_accrued_gross = $accruedData ? (int) ($accruedData->gross_total ?? 0) : 0;
            $payout->total_accrued_fee = $accruedData ? (int) ($accruedData->fee_total ?? 0) : 0;

            // Get earnings for the period(s) of this payout
            if ($payout->period) {
                $periods = array_filter(explode(',', $payout->period));
                sort($periods); // Sort for consistent key generation
                $key = $payout->artist_id . '|' . implode(',', $periods);
                $payout->period_earnings = isset($periodEarningsMap[$key]) ? (int) $periodEarningsMap[$key] : 0;
                $payout->period_gross = isset($periodGrossMap[$key]) ? (int) $periodGrossMap[$key] : 0;
                $payout->period_fee = isset($periodFeeMap[$key]) ? (int) $periodFeeMap[$key] : 0;
            } else {
                $payout->period_earnings = 0;
                $payout->period_gross = 0;
                $payout->period_fee = 0;
            }

            return $payout;
        });

        return $this->success(['payouts' => $payouts]);
    }

    public function approve(Request $request, ArtistPayout $payout)
    {
        $this->authorize('update', $payout);

        if ($payout->status !== 'pending') {
            return $this->error(__('Only pending payouts can be approved'), [], 400);
        }

        if ($payout->period) {
            $periods = array_filter(explode(',', $payout->period));
            if (!empty($periods)) {
                $alreadyPaid = ArtistEarning::where('artist_id', $payout->artist_id)
                    ->whereIn('period', $periods)
                    ->where('status', 'paid')
                    ->exists();

                if ($alreadyPaid) {
                    return $this->error(__('Earnings for this period have already been paid'), [], 400);
                }
            }
        }

        $payout->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
            'admin_note' => $request->get('admin_notes'),
        ]);

        return $this->success(['payout' => $payout->load(['artist', 'payoutMethod'])]);
    }

    public function reject(Request $request, ArtistPayout $payout)
    {
        $this->authorize('update', $payout);

        if ($payout->status !== 'pending') {
            return $this->error(__('Only pending payouts can be rejected'), [], 400);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            // Update payout status
            $payout->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'rejection_reason' => $validated['rejection_reason'],
                'admin_note' => $validated['admin_notes'] ?? null,
            ]);

            // Return the earnings to accrued status
            if ($payout->period) {
                $periods = array_filter(explode(',', $payout->period));
                if (!empty($periods)) {
                    ArtistEarning::where('artist_id', $payout->artist_id)
                        ->whereIn('period', $periods)
                        ->where('status', 'paid')
                        ->update(['status' => 'accrued']);
                }
            }

            DB::commit();

            return $this->success(['payout' => $payout->load(['artist', 'payoutMethod'])]);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error(__('Could not reject payout'), [], 500);
        }
    }

    public function markAsProcessing(ArtistPayout $payout)
    {
        $this->authorize('update', $payout);

        if ($payout->status !== 'approved') {
            return $this->error(__('Only approved payouts can be marked as processing'), [], 400);
        }

        $payout->update(['status' => 'processing']);

        return $this->success(['payout' => $payout->load(['artist', 'payoutMethod'])]);
    }

    public function markAsCompleted(ArtistPayout $payout)
    {
        $this->authorize('update', $payout);

        if (!in_array($payout->status, ['approved', 'processing'])) {
            return $this->error(__('Only approved or processing payouts can be marked as completed'), [], 400);
        }

        if (!$payout->period) {
            return $this->error(__('Payout period is missing'), [], 400);
        }

        DB::beginTransaction();
        try {
            $periods = array_filter(explode(',', $payout->period));
            
            if (empty($periods)) {
                DB::rollBack();
                return $this->error(__('Invalid payout period'), [], 400);
            }

            // Validate period format
            foreach ($periods as $period) {
                if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
                    DB::rollBack();
                    return $this->error(__('Invalid period format: :period. Expected format: YYYY-MM', [
                        'period' => $period
                    ]), [], 400);
                }
            }

            $alreadyPaid = ArtistEarning::where('artist_id', $payout->artist_id)
                ->whereIn('period', $periods)
                ->where('status', 'paid')
                ->exists();

            if ($alreadyPaid) {
                DB::rollBack();
                return $this->error(__('Earnings for this period have already been paid'), [], 400);
            }

            // Mark payout as completed
            $payout->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Mark corresponding earnings as paid for the period(s)
            ArtistEarning::where('artist_id', $payout->artist_id)
                ->whereIn('period', $periods)
                ->where('status', 'accrued')
                ->update(['status' => 'paid']);

            DB::commit();

            return $this->success(['payout' => $payout->load(['artist', 'payoutMethod'])]);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error(__('Could not complete payout'), [], 500);
        }
    }

    public function markAsFailed(Request $request, ArtistPayout $payout)
    {
        $this->authorize('update', $payout);

        if (!in_array($payout->status, ['approved', 'processing'])) {
            return $this->error(__('Only approved or processing payouts can be marked as failed'), [], 400);
        }

        $validated = $request->validate([
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $payout->update([
            'status' => 'failed',
            'admin_note' => $validated['admin_notes'] ?? null,
        ]);

        return $this->success(['payout' => $payout->load(['artist', 'payoutMethod'])]);
    }
}
