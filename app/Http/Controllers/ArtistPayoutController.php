<?php

namespace App\Http\Controllers;

use App\Models\Artist;
use App\Models\ArtistEarning;
use App\Models\ArtistPayout;
use App\Models\ArtistPayoutMethod;
use Carbon\Carbon;
use Common\Core\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ArtistPayoutController extends BaseController
{
    public function index()
    {
        $user = Auth::user();
        $artist = $user->primaryArtist();

        if (!$artist) {
            return $this->error('No artist profile found for this user', [], 404);
        }

        $this->authorize('show', $artist);

        $payouts = ArtistPayout::where('artist_id', $artist->id)
            ->with('payoutMethod')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success(['payouts' => $payouts]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $artist = $user->primaryArtist();

        if (!$artist) {
            return $this->error('No artist profile found for this user', [], 404);
        }

        $this->authorize('update', $artist);

        $validated = $request->validate([
            'payout_method_id' => 'required|exists:artist_payout_methods,id',
            'amount' => 'required|integer|min:1000', // Minimum $10
            'artist_note' => 'nullable|string|max:500',
        ]);

        // Verify payout method belongs to artist
        $payoutMethod = ArtistPayoutMethod::findOrFail($validated['payout_method_id']);
        if ($payoutMethod->artist_id !== $artist->id) {
            abort(403, 'Invalid payout method');
        }


        // Get accrued earnings grouped by period (only those with period set)
        $accruedEarnings = ArtistEarning::where('artist_id', $artist->id)
            ->where('status', 'accrued')
            ->whereNotNull('period')
            ->orderBy('period', 'asc')
            ->get();

        $accruedBalance = $accruedEarnings->sum('net_amount');

        if ($validated['amount'] > $accruedBalance) {
            return $this->error(__('Insufficient balance. Available: $:amount', [
                'amount' => number_format($accruedBalance / 100, 2)
            ]), [], 422);
        }

        // Determine which periods are being requested
        // Only match complete periods (no partial period amounts)
        $amountRemaining = $validated['amount'];
        $periods = [];
        $matchedEarnings = [];
        
        foreach ($accruedEarnings as $earning) {
            if ($amountRemaining <= 0) {
                break;
            }
            // Only include complete periods (exact match, no partial amounts)
            if ($earning->period && $earning->net_amount > 0 && $earning->net_amount <= $amountRemaining) {
                $periods[] = $earning->period;
                $matchedEarnings[] = $earning;
                $amountRemaining -= $earning->net_amount;
            }
        }

        // Validate that we matched the exact amount (must be sum of complete periods)
        $matchedAmount = collect($matchedEarnings)->sum('net_amount');
        if ($matchedAmount !== $validated['amount']) {
            $availablePeriods = $accruedEarnings->pluck('period')->filter()->unique()->implode(', ');
            return $this->error(__('Requested amount must exactly match the sum of one or more complete periods. Available periods: :periods', [
                'periods' => $availablePeriods ?: 'none'
            ]), [], 422);
        }

        if (empty($periods)) {
            return $this->error(__('No matching periods found for the requested amount'), [], 422);
        }

        // Validate period format (YYYY-MM)
        foreach ($periods as $period) {
            if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
                return $this->error(__('Invalid period format: :period. Expected format: YYYY-MM', [
                    'period' => $period
                ]), [], 422);
            }
        }

        // Check for duplicate requests per period
        // Check both exact period matches and comma-separated period strings
        foreach ($periods as $period) {
            $existingRequest = ArtistPayout::where('artist_id', $artist->id)
                ->whereIn('status', ['pending', 'approved', 'processing'])
                ->where(function ($query) use ($period) {
                    $query->where('period', $period)
                        ->orWhere('period', 'like', "{$period},%")
                        ->orWhere('period', 'like', "%,{$period},%")
                        ->orWhere('period', 'like', "%,{$period}");
                })
                ->exists();

            if ($existingRequest) {
                return $this->error(__('Payout already requested for period :period', [
                    'period' => $period
                ]), [], 422);
            }
        }

        // Set period (comma-separated if multiple, single period otherwise)
        $periodString = !empty($periods) ? implode(',', array_unique($periods)) : null;
        
        if (!$periodString) {
            return $this->error(__('No valid periods found for the requested amount'), [], 422);
        }

        // Create payout request
        $payout = ArtistPayout::create([
            'artist_id' => $artist->id,
            'period' => $periodString,
            'payout_method_id' => $validated['payout_method_id'],
            'amount' => $validated['amount'],
            'currency' => 'USD',
            'status' => 'pending',
            'artist_note' => $validated['artist_note'] ?? null,
            'requested_at' => Carbon::now(),
        ]);

        return $this->success([
            'payout' => $payout->load('payoutMethod'),
            'message' => __('Payout request submitted successfully')
        ]);
    }

    public function cancel(ArtistPayout $payout)
    {
        $user = Auth::user();
        $artist = $user->primaryArtist();

        if (!$artist) {
            return $this->error('No artist profile found for this user', [], 404);
        }

        $this->authorize('update', $artist);

        if ($payout->artist_id !== $artist->id) {
            abort(403, 'Unauthorized');
        }

        if ($payout->status !== 'pending') {
            return $this->error(__('Only pending payouts can be cancelled'), [], 422);
        }

        $payout->update(['status' => 'rejected', 'rejected_at' => Carbon::now(), 'admin_note' => 'Cancelled by artist']);

        return $this->success(['message' => __('Payout request cancelled')]);
    }
}
