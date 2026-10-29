<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArtistEarning extends Model
{
    protected $fillable = [
        'artist_id',
        'earning_date',
        'period',
        'followers_count',
        'gross_amount',
        'platform_fee',
        'net_amount',
        'status',
    ];

    protected $casts = [
        'earning_date' => 'date',
        'followers_count' => 'integer',
        'gross_amount' => 'integer',
        'platform_fee' => 'integer',
        'net_amount' => 'integer',
    ];

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    /**
     * Calculate earnings for a given followers count.
     * €0.10 per follower (monthly for accrued, daily for pending analytics)
     */
    public static function calculateEarnings(int $followersCount): array
    {
        // €0.10 per follower = 10 cents
        $grossAmount = $followersCount * 10;

        // 10% platform fee
        $platformFee = (int) ($grossAmount * 0.10);

        // Net amount after fee
        $netAmount = $grossAmount - $platformFee;

        return [
            'gross_amount' => $grossAmount,
            'platform_fee' => $platformFee,
            'net_amount' => $netAmount,
        ];
    }

    public static function getPeriodForDate(Carbon $date): string
    {
        return $date->format('Y-m');
    }

    public static function getPreviousPeriod(Carbon $date = null): string
    {
        $date = $date ?? Carbon::now();
        return $date->copy()->subMonth()->format('Y-m');
    }

    public function scopeForPeriod($query, string $period)
    {
        return $query->where('period', $period);
    }

    public function scopeAccrued($query)
    {
        return $query->where('status', 'accrued');
    }

    public function scopePayable($query)
    {
        return $query->where('status', 'accrued');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
