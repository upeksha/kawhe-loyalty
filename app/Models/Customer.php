<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'birthday',
        'email_verified_at',
        'email_verification_token_hash',
        'email_verification_expires_at',
        'email_verification_sent_at',
    ];

    protected $casts = [
        'birthday' => 'date',
        'email_verified_at' => 'datetime',
        'email_verification_expires_at' => 'datetime',
        'email_verification_sent_at' => 'datetime',
    ];

    public function loyaltyAccounts(): HasMany
    {
        return $this->hasMany(LoyaltyAccount::class);
    }
}
