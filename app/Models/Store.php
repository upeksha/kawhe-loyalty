<?php

namespace App\Models;

use App\Support\StoreAssets;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Store extends Model
{
    /** @use HasFactory<\Database\Factories\StoreFactory> */
    use HasFactory;

    /**
     * Get a query builder for stores accessible by the given user.
     * Super admins can access all stores, regular users only their own.
     */
    public static function queryForUser($user)
    {
        if ($user && $user->isSuperAdmin()) {
            return static::query();
        }

        return $user ? $user->stores() : static::whereRaw('0 = 1');
    }

    /** Length of join short code (e.g. /j/abc12x). */
    public const JOIN_SHORT_CODE_LENGTH = 6;

    /** Alphabet for join short code (no I,O,0,1 for readability). */
    private const JOIN_SHORT_CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** Onboarding wizard step: card_design | customer_form | card_ready | continue_trial. Null = completed or legacy. */
    public const ONBOARDING_STEP_CARD_DESIGN = 'card_design';
    public const ONBOARDING_STEP_CUSTOMER_FORM = 'customer_form';
    public const ONBOARDING_STEP_CARD_READY = 'card_ready';
    public const ONBOARDING_STEP_CONTINUE_TRIAL = 'continue_trial';

    protected $fillable = [
        'name',
        'slug',
        'address',
        'reward_target',
        'reward_title',
        'join_token',
        'join_short_code',
        'brand_color',
        'logo_path',
        'background_color',
        'pass_logo_path',
        'pass_hero_image_path',
        'require_verification_for_redemption',
        'onboarding_step',
        'onboarding_completed_at',
        'registration_form_config',
    ];

    protected $casts = [
        'onboarding_completed_at' => 'datetime',
        'registration_form_config' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($store) {
            if (empty($store->slug)) {
                $store->slug = Str::slug($store->name) . '-' . Str::random(6);
            }
            if (empty($store->join_token)) {
                $store->join_token = Str::random(32);
            }
            if (empty($store->join_short_code)) {
                $store->join_short_code = self::generateJoinShortCode();
            }
        });
    }

    /**
     * Generate a unique 6-char join short code for /j/{code} URLs.
     */
    public static function generateJoinShortCode(): string
    {
        $alphabet = self::JOIN_SHORT_CODE_ALPHABET;
        $len = strlen($alphabet);
        do {
            $code = '';
            for ($i = 0; $i < self::JOIN_SHORT_CODE_LENGTH; $i++) {
                $code .= $alphabet[random_int(0, $len - 1)];
            }
        } while (static::where('join_short_code', $code)->exists());
        return $code;
    }

    /**
     * Get the public join URL (short form when available).
     */
    public function getJoinUrlAttribute(): string
    {
        if (! empty($this->join_short_code)) {
            return route('join.short', ['code' => $this->join_short_code]);
        }
        return route('join.index', ['slug' => $this->slug, 't' => $this->join_token]);
    }

    /**
     * Default registration form config for customer join (MVP).
     */
    public static function defaultRegistrationFormConfig(): array
    {
        return [
            'email' => ['enabled' => true, 'required' => true],
            'first_name' => ['enabled' => true, 'required' => false],
            'last_name' => ['enabled' => false, 'required' => false],
            'phone' => ['enabled' => false, 'required' => false],
            'birthday' => ['enabled' => false, 'required' => false],
        ];
    }

    /**
     * Get effective registration form config (merge with defaults).
     */
    public function getRegistrationFormConfigAttribute($value): array
    {
        $decoded = is_array($value) ? $value : (is_string($value) ? json_decode($value, true) : []);
        return array_merge(self::defaultRegistrationFormConfig(), $decoded ?: []);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return StoreAssets::url($this->logo_path);
    }

    public function getPassLogoUrlAttribute(): ?string
    {
        return StoreAssets::url($this->pass_logo_path);
    }

    public function getPassHeroImageUrlAttribute(): ?string
    {
        return StoreAssets::url($this->pass_hero_image_path);
    }
}
