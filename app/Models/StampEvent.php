<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StampEvent extends Model
{
    protected $fillable = [
        'loyalty_account_id',
        'store_id',
        'user_id',
        'type',
    ];

    public function loyaltyAccount(): BelongsTo
    {
        return $this->belongsTo(LoyaltyAccount::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
