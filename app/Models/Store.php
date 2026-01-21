<?php

namespace App\Models;

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

    protected $fillable = [
        'name',
        'slug',
        'address',
        'reward_target',
        'reward_title',
        'join_token',
        'brand_color',
        'logo_path',
        'background_color',
        'pass_logo_path',
        'pass_hero_image_path',
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
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
