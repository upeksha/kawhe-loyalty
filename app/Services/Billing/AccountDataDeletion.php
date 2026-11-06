<?php

namespace App\Services\Billing;

use App\Models\AccountFileCleanup;
use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyProgram;
use App\Models\Store;
use App\Models\User;
use App\Services\Wallet\Apple\AppleWalletSerial;
use App\Services\Wallet\WalletIdentity;
use App\Support\StoreAssets;
use Illuminate\Support\Facades\DB;

class AccountDataDeletion
{
    public const IMAGE_FIELDS = ['logo_path', 'pass_logo_path', 'pass_hero_image_path', 'card_photo_path', 'stamp_icon_path'];

    public const UPLOAD_DIRECTORIES = ['logos', 'pass-logos', 'pass-heroes', 'card-designs'];

    /** Called inside AccountDeletionService's transaction, after billing clearance. */
    public function deleteOwnedData(User $user): void
    {
        $stores = Store::withTrashed()->where('user_id', $user->id)->lockForUpdate()->get();
        $storeIds = $stores->modelKeys();
        $programs = LoyaltyProgram::withTrashed()->whereIn('store_id', $storeIds)->lockForUpdate()->get();
        $accounts = LoyaltyAccount::whereIn('store_id', $storeIds)->lockForUpdate()->get(['id', 'store_id', 'customer_id']);
        $customerIds = $accounts->pluck('customer_id')->unique()->all();
        $models = $stores->concat($programs);
        $files = $models->flatMap(fn ($model) => array_values($model->only(self::IMAGE_FIELDS)))
            ->merge($programs->flatMap(fn ($program) => LoyaltyProgram::walletManifestPaths($program->wallet_asset_manifest)))
            ->filter()->unique()->values()->all();
        $serials = $accounts->flatMap(fn ($account) => [
            AppleWalletSerial::fromAccount($account),
            WalletIdentity::prefix("kawhe-{$account->store_id}-{$account->customer_id}"),
        ])->unique()->values()->all();

        // Commit the cleanup intent with the database deletion. No storage/network
        // operations here: a rollback must never remove a surviving account's files.
        AccountFileCleanup::create([
            'deleted_user_id' => $user->id,
            'available_at' => now()->addMinutes(5), // allow in-flight render requests to finish
            'payload' => [
                'asset_disk' => StoreAssets::diskName(),
                'files' => $files,
                'store_ids' => $storeIds,
                'program_ids' => $programs->modelKeys(),
                'account_ids' => $accounts->modelKeys(),
                'styles' => $models->map(fn ($model) => $model->only([
                    'card_design', 'card_pattern', 'stamp_icon', 'stamp_icon_path',
                    'card_photo_path', 'brand_color', 'background_color',
                ]))->all(),
                'pass_disk' => config('passgenerator.storage_disk') ?: 'local',
                'pass_directory' => config('passgenerator.storage_path'),
                'pass_serials' => $serials,
            ],
        ]);

        DB::table('support_audit_logs')->where(function ($query) use ($storeIds, $accounts, $user) {
            $query->whereIn('store_id', $storeIds)
                ->orWhereIn('loyalty_account_id', $accounts->modelKeys())
                ->orWhere(fn ($q) => $q->where('actor_user_id', $user->id)->whereNull('store_id'));
        })->delete();
        // Explicitly use SQL cascading, not model forceDelete hooks that delete
        // files inside the transaction. Includes archived stores and cards.
        DB::table('apple_wallet_registrations')->whereIn('serial_number', $serials)->delete();
        DB::table('stores')->whereIn('id', $storeIds)->delete();

        // Preserve other cafés' ledger totals/history while removing the deleted
        // staff member's technical identifiers. The FK clears the actor ID.
        foreach (['stamp_events', 'points_transactions'] as $table) {
            DB::table($table)->where('user_id', $user->id)->update(['ip_address' => null, 'user_agent' => null]);
        }

        // A customer can belong to several merchants. Only remove identities that
        // have no remaining card or recovery/enrolment at another store.
        Customer::whereIn('id', $customerIds)->whereDoesntHave('loyaltyAccounts')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('customer_card_recoveries')
                ->whereColumn('customer_id', 'customers.id')->where('expires_at', '>', now()))->delete();
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        DB::table('store_staff_invitations')->where('email', $user->email)->delete();
    }
}
