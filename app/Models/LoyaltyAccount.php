<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LoyaltyAccount extends Model
{
    protected $fillable = [
        'store_id',
        'customer_id',
        'stamp_count',
        'public_token',
        'redeem_token',
        'last_stamped_at',
        'reward_available_at',
        'reward_redeemed_at',
    ];

    protected $casts = [
        'last_stamped_at' => 'datetime',
        'reward_available_at' => 'datetime',
        'reward_redeemed_at' => 'datetime',
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
}
