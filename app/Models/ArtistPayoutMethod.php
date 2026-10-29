<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArtistPayoutMethod extends Model
{
    protected $fillable = [
        'artist_id',
        'type',
        'account_holder_name',
        'bank_name',
        'account_number',
        'routing_number',
        'swift_code',
        'iban',
        'paypal_email',
        'stripe_account_id',
        'country',
        'is_default',
        'is_verified',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_verified' => 'boolean',
    ];

    protected $hidden = [
        'account_number',
        'routing_number',
        'iban',
    ];

    protected $appends = ['masked_account', 'masked_iban', 'masked_account_number'];

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function getMaskedAccountAttribute(): ?string
    {
        if ($this->type === 'paypal') {
            return $this->paypal_email;
        }

        if ($this->type === 'bank_transfer') {
            $account = $this->account_number ?? $this->iban;
            if ($account && strlen($account) > 4) {
                return '****' . substr($account, -4);
            }
        }

        return null;
    }

    public function getMaskedIbanAttribute(): ?string
    {
        if ($this->iban && strlen($this->iban) > 4) {
            return '****' . substr($this->iban, -4);
        }
        return $this->iban;
    }

    public function getMaskedAccountNumberAttribute(): ?string
    {
        if ($this->account_number && strlen($this->account_number) > 4) {
            return '****' . substr($this->account_number, -4);
        }
        return $this->account_number;
    }
}
