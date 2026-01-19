<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppleWalletRegistration extends Model
{
    protected $fillable = [
        'device_library_identifier',
        'push_token',
        'pass_type_identifier',
        'serial_number',
        'loyalty_account_id',
        'active',
        'last_registered_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'last_registered_at' => 'datetime',
    ];

    /**
     * Get the loyalty account this registration belongs to.
     */
    public function loyaltyAccount(): BelongsTo
    {
        return $this->belongsTo(LoyaltyAccount::class);
    }

    /**
     * Scope to only active registrations.
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
