<?php

namespace App\Models;

use App\Notifications\MerchantResetPassword;
use App\Notifications\MerchantVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use Billable, HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_super_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'is_staff_account' => 'boolean',
        ];
    }

    /**
     * Check if the user is a super admin.
     */
    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new MerchantVerifyEmail($this->email));
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new MerchantResetPassword($token, $this->getEmailForPasswordReset()));
    }

    public function scannerStores()
    {
        return Store::query()->where(function ($query) {
            $query->where('user_id', $this->id)->orWhereIn('id', StoreStaff::select('store_id')
                ->where('user_id', $this->id)->where(fn ($q) => $q->where('can_stamp', true)->orWhere('can_redeem', true)));
        });
    }

    public function canOperateStore(Store $store, ?string $permission = null, bool $lock = false): bool
    {
        if ($store->trashed()) {
            return false;
        }
        if ($store->user_id === $this->id || $this->isSuperAdmin()) {
            return true;
        }
        $query = StoreStaff::where('store_id', $store->id)->where('user_id', $this->id);
        $member = ($lock ? $query->lockForUpdate() : $query)->first();
        if (! $member) {
            return false;
        }

        return match ($permission) {
            'stamp' => $member->can_stamp,
            'redeem' => $member->can_redeem,
            default => $member->can_stamp || $member->can_redeem,
        };
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }
}
