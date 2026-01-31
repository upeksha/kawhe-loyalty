<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class LoyaltyAccount extends Model
{
    use HasFactory, Notifiable;

    /**
     * Token length for public_token and redeem_token (shorter = simpler QR and manual code).
     * Existing 40-char tokens remain valid; new accounts use this length for faster scanning.
     */
    public const PUBLIC_TOKEN_LENGTH = 16;
    public const REDEEM_TOKEN_LENGTH = 16;

    /**
     * Route notifications for the mail channel.
     *
     * @return string
     */
    public function routeNotificationForMail()
    {
        return $this->customer->email;
    }
    protected $fillable = [
        'store_id',
        'customer_id',
        'stamp_count',
        'reward_balance',
        'public_token',
        'wallet_auth_token',
        'redeem_token',
        'last_stamped_at',
        'reward_available_at',
        'reward_redeemed_at',
        'verified_at',
        'email_verification_token_hash',
        'email_verification_expires_at',
        'email_verification_sent_at',
        'version',
    ];

    protected $casts = [
        'last_stamped_at' => 'datetime',
        'reward_available_at' => 'datetime',
        'reward_redeemed_at' => 'datetime',
        'verified_at' => 'datetime',
        'email_verification_expires_at' => 'datetime',
        'email_verification_sent_at' => 'datetime',
        'version' => 'integer',
        'reward_balance' => 'integer',
    ];

    protected $attributes = [
        'reward_balance' => 0,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($account) {
            if (empty($account->public_token)) {
                $account->public_token = Str::random(self::PUBLIC_TOKEN_LENGTH);
            }
            if (empty($account->wallet_auth_token)) {
                $account->wallet_auth_token = Str::random(40); // Keep 40 for auth security
            }
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function pointsTransactions()
    {
        return $this->hasMany(PointsTransaction::class);
    }

    /**
     * Calculate current balance from ledger (for verification/audit)
     * Note: We still use stamp_count for performance, but this provides audit trail
     */
    public function calculateBalanceFromLedger(): int
    {
        return $this->pointsTransactions()
            ->sum('points');
    }
}
