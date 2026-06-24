<?php

namespace App\Models;

use App\Support\StoreAssets;
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

    /** 4-char manual entry code (e.g. A3CX), unique per store. */
    public const MANUAL_ENTRY_CODE_LENGTH = 4;

    /** Character set for manual entry code (excludes ambiguous I,O,0,1). */
    private const MANUAL_ENTRY_CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

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
        'loyalty_program_id',
        'customer_id',
        'stamp_count',
        'reward_balance',
        'public_token',
        'wallet_auth_token',
        'redeem_token',
        'manual_entry_code',
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
            if (empty($account->loyalty_program_id) && ! empty($account->store_id)) {
                $account->loyalty_program_id = Store::withTrashed()
                    ->find($account->store_id)
                    ?->resolvedDefaultProgram()
                    ?->id;
            }
            if (empty($account->manual_entry_code) && $account->store_id) {
                $account->manual_entry_code = self::generateManualEntryCode($account->store_id);
            }
        });

        static::deleting(function (self $account) {
            StoreAssets::deleteGeneratedStampStripsForAccount($account->id);
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class)->withTrashed();
    }

    public function loyaltyProgram(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class)->withTrashed();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function pointsTransactions()
    {
        return $this->hasMany(PointsTransaction::class);
    }

    public function resolvedProgram(): ?LoyaltyProgram
    {
        if ($this->relationLoaded('loyaltyProgram') && $this->loyaltyProgram) {
            return $this->loyaltyProgram;
        }

        if ($this->loyalty_program_id) {
            return $this->loyaltyProgram()->first();
        }

        $store = $this->relationLoaded('store') ? $this->store : $this->store()->first();

        return $store?->resolvedDefaultProgram();
    }

    public function getRewardTargetAttribute(): int
    {
        return (int) ($this->resolvedProgram()?->reward_target ?? $this->store?->getRawOriginal('reward_target') ?? config('loyalty.reward_target', 10));
    }

    public function getRewardTitleAttribute(): string
    {
        return (string) ($this->resolvedProgram()?->reward_title ?? $this->store?->getRawOriginal('reward_title') ?? 'Rewards');
    }

    public function getProgramNameAttribute(): string
    {
        return (string) ($this->resolvedProgram()?->name ?? $this->reward_title);
    }

    public function getProgramBrandColorAttribute(): ?string
    {
        return $this->resolvedProgram()?->brand_color ?? $this->store?->brand_color;
    }

    public function getProgramBackgroundColorAttribute(): ?string
    {
        return $this->resolvedProgram()?->background_color ?? $this->store?->background_color;
    }

    public function getProgramLogoUrlAttribute(): ?string
    {
        $program = $this->resolvedProgram();
        if ($program?->logo_path) {
            return $program->logo_url;
        }

        return $this->store?->logo_url;
    }

    public function getRequiresVerificationForRedemptionAttribute(): bool
    {
        return (bool) ($this->resolvedProgram()?->require_verification_for_redemption ?? $this->store?->require_verification_for_redemption ?? true);
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

    /**
     * Generate a 4-char manual entry code unique for the given store.
     */
    public static function generateManualEntryCode(int $storeId): string
    {
        $chars = self::MANUAL_ENTRY_CHARS;
        $max = 50;
        for ($i = 0; $i < $max; $i++) {
            $code = '';
            for ($j = 0; $j < self::MANUAL_ENTRY_CODE_LENGTH; $j++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $exists = static::where('store_id', $storeId)->where('manual_entry_code', $code)->exists();
            if (!$exists) {
                return $code;
            }
        }
        return strtoupper(Str::random(self::MANUAL_ENTRY_CODE_LENGTH));
    }
}
