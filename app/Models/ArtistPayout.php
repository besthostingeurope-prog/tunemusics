<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Common\Auth\BaseUser;

class ArtistPayout extends Model
{
    protected $fillable = [
        'artist_id',
        'period',
        'payout_method_id',
        'amount',
        'currency',
        'status',
        'artist_note',
        'admin_note',
        'requested_at',
        'approved_at',
        'completed_at',
        'rejected_at',
        'rejection_reason',
        'approved_by',
        'transaction_id',
    ];

    protected $casts = [
        'amount' => 'integer',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function payoutMethod(): BelongsTo
    {
        return $this->belongsTo(ArtistPayoutMethod::class, 'payout_method_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(BaseUser::class, 'approved_by');
    }

    public static function getPeriodForDate(Carbon $date): string
    {
        return $date->format('Y-m');
    }

    public function scopeForPeriod($query, string $period)
    {
        return $query->where('period', $period);
    }

    public function scopeRequestedForPeriod($query, int $artistId, string $period)
    {
        return $query->where('artist_id', $artistId)
            ->where('period', $period)
            ->whereIn('status', ['pending', 'approved', 'processing']);
    }
}
