<?php

namespace App\Models;

use App\Support\StoreAssets;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Store extends Model
{
    /** @use HasFactory<\Database\Factories\StoreFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get a query builder for stores accessible by the given user.
     * Super admins can access all stores, regular users only their own.
     */
    public static function queryForUser($user, bool $includeArchived = false)
    {
        if ($user && $user->isSuperAdmin()) {
            $query = static::query();

            return $includeArchived ? $query->withTrashed() : $query;
        }

        if (! $user) {
            return static::whereRaw('0 = 1');
        }

        $query = $user->stores();

        return $includeArchived ? $query->withTrashed() : $query;
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

    public const DEFAULT_BRAND_COLOR = '#0EA5E9';

    public const DEFAULT_BACKGROUND_COLOR = '#1F2937';

    protected $fillable = [
        'name',
        'default_loyalty_program_id',
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
        'deleted_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($store) {
            if (empty($store->slug)) {
                $store->slug = Str::slug($store->name).'-'.Str::random(6);
            }
            if (empty($store->join_token)) {
                $store->join_token = Str::random(32);
            }
            if (empty($store->join_short_code)) {
                $store->join_short_code = self::generateJoinShortCode();
            }
            if (empty($store->brand_color)) {
                $store->brand_color = self::DEFAULT_BRAND_COLOR;
            }
            if (empty($store->background_color)) {
                $store->background_color = self::DEFAULT_BACKGROUND_COLOR;
            }
            if (empty($store->registration_form_config)) {
                $store->registration_form_config = self::defaultRegistrationFormConfig();
            }
        });

        static::deleting(function (self $store) {
            if (! $store->isForceDeleting()) {
                return;
            }

            StoreAssets::delete($store->logo_path);
            StoreAssets::delete($store->pass_logo_path);
            StoreAssets::delete($store->pass_hero_image_path);
            StoreAssets::deleteGeneratedStampStripsForStore($store->id);
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

    public function loyaltyPrograms(): HasMany
    {
        return $this->hasMany(LoyaltyProgram::class)->orderBy('sort_order')->orderBy('id');
    }

    public function defaultLoyaltyProgram(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class, 'default_loyalty_program_id')->withTrashed();
    }

    public function defaultProgram(): HasOne
    {
        return $this->hasOne(LoyaltyProgram::class)->where('is_default', true)->withTrashed();
    }

    public function resolvedDefaultProgram(): ?LoyaltyProgram
    {
        if ($this->relationLoaded('defaultLoyaltyProgram') && $this->defaultLoyaltyProgram) {
            return $this->defaultLoyaltyProgram;
        }

        if ($this->default_loyalty_program_id) {
            return $this->defaultLoyaltyProgram()->first();
        }

        return $this->defaultProgram()->first() ?? $this->ensureDefaultProgramExists();
    }

    public function ensureDefaultProgramExists(): ?LoyaltyProgram
    {
        if (! $this->exists) {
            return null;
        }

        return DB::transaction(function () {
            $store = self::query()->lockForUpdate()->find($this->id);

            if (! $store) {
                return null;
            }

            if ($store->default_loyalty_program_id) {
                $program = $store->defaultLoyaltyProgram()->first();
                if ($program) {
                    return $program;
                }
            }

            $program = $store->defaultProgram()->first();
            if ($program) {
                if ($store->default_loyalty_program_id !== $program->id) {
                    $store->forceFill(['default_loyalty_program_id' => $program->id])->save();
                }

                return $program;
            }

            $program = $store->loyaltyPrograms()->create([
                'name' => $store->reward_title,
                'slug' => $store->slug,
                'reward_target' => $store->reward_target,
                'reward_title' => $store->reward_title,
                'join_token' => $store->join_token,
                'join_short_code' => $store->join_short_code,
                'brand_color' => $store->brand_color,
                'background_color' => $store->background_color,
                'logo_path' => $store->logo_path,
                'pass_logo_path' => $store->pass_logo_path,
                'pass_hero_image_path' => $store->pass_hero_image_path,
                'require_verification_for_redemption' => $store->require_verification_for_redemption ?? true,
                'registration_form_config' => $store->registration_form_config,
                'is_default' => true,
                'sort_order' => 1,
            ]);

            $store->forceFill(['default_loyalty_program_id' => $program->id])->save();

            return $program;
        });
    }

    /**
     * Push store-level onboarding fields to the default loyalty program so
     * customer join pages reflect what the merchant configured in the wizard.
     */
    public function syncDefaultProgramFromStore(): ?LoyaltyProgram
    {
        if (! $this->exists) {
            return null;
        }

        $program = $this->resolvedDefaultProgram();

        if (! $program) {
            return null;
        }

        $program->forceFill([
            'name' => $this->reward_title ?: ($this->name.' Loyalty Card'),
            'reward_target' => $this->reward_target,
            'reward_title' => $this->reward_title,
            'brand_color' => $this->brand_color,
            'background_color' => $this->background_color,
            'logo_path' => $this->logo_path,
            'pass_logo_path' => $this->pass_logo_path,
            'pass_hero_image_path' => $this->pass_hero_image_path,
            'require_verification_for_redemption' => $this->require_verification_for_redemption ?? true,
            'registration_form_config' => $this->registration_form_config,
        ])->save();

        return $program->fresh();
    }

    public function getIsArchivedAttribute(): bool
    {
        return $this->trashed();
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
