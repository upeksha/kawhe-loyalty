<?php

namespace App\Models;

use App\Support\StoreAssets;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class LoyaltyProgram extends Model
{
    use HasFactory, SoftDeletes;

    public const JOIN_SHORT_CODE_LENGTH = 6;

    private const JOIN_SHORT_CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    protected $fillable = [
        'store_id',
        'name',
        'slug',
        'reward_target',
        'reward_title',
        'join_token',
        'join_short_code',
        'brand_color',
        'logo_path',
        'background_color',
        'pass_logo_path',
        'pass_hero_image_path',
        'wallet_design_version',
        'wallet_design_hash',
        'wallet_asset_manifest',
        'wallet_assets_generated_at',
        'wallet_branding_updated_at',
        'require_verification_for_redemption',
        'registration_form_config',
        'sort_order',
        'is_default',
    ];

    protected $casts = [
        'registration_form_config' => 'array',
        'wallet_asset_manifest' => 'array',
        'wallet_design_version' => 'integer',
        'wallet_assets_generated_at' => 'datetime',
        'wallet_branding_updated_at' => 'datetime',
        'is_default' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $program) {
            if (empty($program->slug)) {
                $base = $program->store?->name ?: $program->name ?: 'program';
                $program->slug = Str::slug($base).'-'.Str::random(6);
            }

            if (empty($program->join_token)) {
                $program->join_token = Str::random(32);
            }

            if (empty($program->join_short_code)) {
                $program->join_short_code = self::generateJoinShortCode();
            }

            if (empty($program->registration_form_config)) {
                $program->registration_form_config = Store::defaultRegistrationFormConfig();
            }

            if (empty($program->name)) {
                $program->name = $program->reward_title ?: 'Loyalty Card';
            }
        });

        static::deleting(function (self $program) {
            if (! $program->isForceDeleting()) {
                return;
            }

            StoreAssets::delete($program->logo_path);
            StoreAssets::delete($program->pass_logo_path);
            StoreAssets::delete($program->pass_hero_image_path);

            foreach (self::walletManifestPaths($program->wallet_asset_manifest) as $path) {
                StoreAssets::delete($path);
            }
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class)->withTrashed();
    }

    public function loyaltyAccounts(): HasMany
    {
        return $this->hasMany(LoyaltyAccount::class);
    }

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

    public function getJoinUrlAttribute(): string
    {
        if (! empty($this->join_short_code)) {
            return route('join.short', ['code' => $this->join_short_code]);
        }

        return route('join.index', ['slug' => $this->slug, 't' => $this->join_token]);
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

    public function walletAssetPath(string $platform, string $asset): ?string
    {
        $path = data_get($this->wallet_asset_manifest, "{$platform}.{$asset}");

        return is_string($path) && $path !== '' ? $path : null;
    }

    public function walletAssetUrl(string $platform, string $asset): ?string
    {
        return StoreAssets::url($this->walletAssetPath($platform, $asset));
    }

    /**
     * @return array<int, string>
     */
    public static function walletManifestPaths(?array $manifest): array
    {
        if (! $manifest) {
            return [];
        }

        $paths = [];
        array_walk_recursive($manifest, function ($value, $key) use (&$paths) {
            if (is_string($value) && str_starts_with($value, 'wallet/programs/')) {
                $paths[] = $value;
            }
        });

        return array_values(array_unique($paths));
    }
}
