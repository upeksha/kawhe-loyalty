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
        'public_token',
        'redeem_token',
        'last_stamped_at',
        'reward_available_at',
        'reward_redeemed_at',
        'verified_at',
        'version',
    ];

    protected $casts = [
        'last_stamped_at' => 'datetime',
        'reward_available_at' => 'datetime',
        'reward_redeemed_at' => 'datetime',
        'version' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($account) {
            if (empty($account->public_token)) {
                $account->public_token = Str::random(40);
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
