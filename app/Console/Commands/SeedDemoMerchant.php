<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyProgram;
use App\Models\PointsTransaction;
use App\Models\StampEvent;
use App\Models\Store;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Cashier\Subscription;

class SeedDemoMerchant extends Command
{
    protected $signature = 'kawhe:seed-demo-merchant {email : Merchant login email}';

    protected $description = 'Seed a complete demo dataset (stores, programs, customers, activity) for a merchant account';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User with email '{$email}' not found.");

            return self::FAILURE;
        }

        $this->info("Seeding demo data for {$user->name} ({$user->email})");

        DB::transaction(function () use ($user) {
            $this->ensureProSubscription($user);

            $primaryStore = $user->stores()->orderBy('id')->first();

            if (! $primaryStore) {
                $primaryStore = $user->stores()->create([
                    'name' => 'Pink Dairy',
                    'address' => '12 Cuba Street, Wellington',
                    'reward_target' => 8,
                    'reward_title' => 'Free coffee',
                    'brand_color' => '#3D7659',
                    'background_color' => '#1F2937',
                    'onboarding_completed_at' => now(),
                ]);
            }

            $this->enrichStore($primaryStore, [
                'name' => 'Pink Dairy — Cuba St',
                'address' => '12 Cuba Street, Te Aro, Wellington 6011',
                'reward_target' => 8,
                'reward_title' => 'Free coffee',
                'brand_color' => '#3D7659',
                'background_color' => '#1F2937',
                'registration_form_config' => [
                    'email' => ['enabled' => true, 'required' => true],
                    'first_name' => ['enabled' => true, 'required' => true],
                    'last_name' => ['enabled' => true, 'required' => false],
                    'phone' => ['enabled' => true, 'required' => false],
                    'birthday' => ['enabled' => true, 'required' => false],
                ],
            ]);

            $coffeeProgram = $primaryStore->resolvedDefaultProgram();
            $this->enrichProgram($coffeeProgram, [
                'name' => 'Coffee Rewards',
                'reward_target' => 8,
                'reward_title' => 'Free coffee',
                'brand_color' => '#3D7659',
                'background_color' => '#1F2937',
            ]);

            $icedProgram = $this->ensureProgram($primaryStore, [
                'name' => 'Iced Drinks Club',
                'reward_target' => 6,
                'reward_title' => 'Free iced latte',
                'brand_color' => '#0EA5E9',
                'background_color' => '#0F172A',
                'sort_order' => 2,
                'is_default' => false,
            ]);

            $pastryProgram = $this->ensureProgram($primaryStore, [
                'name' => 'Pastry Pass',
                'reward_target' => 5,
                'reward_title' => 'Free pastry',
                'brand_color' => '#D97706',
                'background_color' => '#292524',
                'sort_order' => 3,
                'is_default' => false,
            ]);

            $cbdStore = $this->ensureStore($user, 'Pink Dairy — Lambton Quay', '220 Lambton Quay, Wellington 6011');
            $cbdProgram = $cbdStore->resolvedDefaultProgram();

            $airportStore = $this->ensureStore($user, 'Pink Dairy — Airport', 'Terminal 1, Wellington Airport 6022');
            $airportProgram = $airportStore->resolvedDefaultProgram();

            $this->seedProgramCustomers($user, $primaryStore, $coffeeProgram, 28, 'coffee');
            $this->seedProgramCustomers($user, $primaryStore, $icedProgram, 12, 'iced');
            $this->seedProgramCustomers($user, $primaryStore, $pastryProgram, 10, 'pastry');
            $this->seedProgramCustomers($user, $cbdStore, $cbdProgram, 14, 'cbd');
            $this->seedProgramCustomers($user, $airportStore, $airportProgram, 16, 'airport');
        });

        $stores = $user->stores()->count();
        $programs = LoyaltyProgram::whereIn('store_id', $user->stores()->pluck('id'))->count();
        $accounts = LoyaltyAccount::whereIn('store_id', $user->stores()->pluck('id'))->count();
        $events = StampEvent::whereIn('store_id', $user->stores()->pluck('id'))->count();

        $this->newLine();
        $this->info('Demo seed complete.');
        $this->line("  Stores: {$stores}");
        $this->line("  Loyalty programs: {$programs}");
        $this->line("  Customer cards: {$accounts}");
        $this->line("  Stamp/redeem events: {$events}");
        $this->line('  Plan: Pro (active subscription for testing)');

        $primary = $user->stores()->orderBy('id')->first();
        if ($primary) {
            $program = $primary->resolvedDefaultProgram();
            if ($program) {
                $joinUrl = route('join.index', ['slug' => $program->slug, 't' => $program->join_token]);
                $this->line("  Sample join URL: {$joinUrl}");
            }
        }

        return self::SUCCESS;
    }

    private function ensureProSubscription(User $user): void
    {
        if (! $user->stripe_id) {
            $user->forceFill(['stripe_id' => 'cus_demo_'.Str::lower(Str::random(14))])->save();
        }

        $subscription = $user->subscription('default');

        if ($subscription && in_array($subscription->stripe_status, ['active', 'trialing'], true) && ! $subscription->ends_at) {
            return;
        }

        Subscription::query()->updateOrCreate(
            ['user_id' => $user->id, 'name' => 'default'],
            [
                'type' => 'default',
                'stripe_id' => 'sub_demo_'.Str::lower(Str::random(14)),
                'stripe_status' => 'active',
                'quantity' => 1,
                'ends_at' => null,
            ]
        );
    }

    private function enrichStore(Store $store, array $attributes): void
    {
        $store->forceFill(array_merge($attributes, [
            'onboarding_step' => null,
            'onboarding_completed_at' => $store->onboarding_completed_at ?? now(),
            'require_verification_for_redemption' => true,
        ]))->save();

        $store->syncDefaultProgramFromStore();
    }

    private function enrichProgram(?LoyaltyProgram $program, array $attributes): void
    {
        if (! $program) {
            return;
        }

        $program->forceFill(array_merge($attributes, [
            'require_verification_for_redemption' => true,
            'registration_form_config' => Store::defaultRegistrationFormConfig(),
        ]))->save();
    }

    private function ensureStore(User $user, string $name, string $address): Store
    {
        $existing = $user->stores()->where('name', $name)->first();

        if ($existing) {
            $this->enrichStore($existing, [
                'name' => $name,
                'address' => $address,
                'reward_target' => 8,
                'reward_title' => 'Free coffee',
                'brand_color' => '#3D7659',
                'background_color' => '#1F2937',
            ]);

            return $existing;
        }

        return $user->stores()->create([
            'name' => $name,
            'address' => $address,
            'reward_target' => 8,
            'reward_title' => 'Free coffee',
            'brand_color' => '#3D7659',
            'background_color' => '#1F2937',
            'onboarding_completed_at' => now(),
            'require_verification_for_redemption' => true,
        ]);
    }

    private function ensureProgram(Store $store, array $attributes): LoyaltyProgram
    {
        $existing = $store->loyaltyPrograms()
            ->where('name', $attributes['name'])
            ->first();

        if ($existing) {
            $existing->forceFill($attributes)->save();

            return $existing;
        }

        return $store->loyaltyPrograms()->create(array_merge([
            'require_verification_for_redemption' => true,
            'registration_form_config' => Store::defaultRegistrationFormConfig(),
        ], $attributes));
    }

    private function seedProgramCustomers(User $merchant, Store $store, ?LoyaltyProgram $program, int $count, string $prefix): void
    {
        if (! $program) {
            return;
        }

        $target = (int) $program->reward_target;
        $existing = LoyaltyAccount::query()
            ->where('store_id', $store->id)
            ->where('loyalty_program_id', $program->id)
            ->count();

        $toCreate = max(0, $count - $existing);

        if ($toCreate === 0) {
            return;
        }

        $firstNames = ['Aroha', 'Mia', 'Noah', 'Isla', 'Leo', 'Ruby', 'Jack', 'Emma', 'Liam', 'Olivia', 'Sophie', 'James'];
        $lastNames = ['Ngata', 'Singh', 'Patel', 'Brown', 'Wilson', 'Chen', 'Taylor', 'Martin', 'Walker', 'King'];

        $bar = $this->output->createProgressBar($toCreate);
        $bar->setMessage("Seeding {$program->name}");
        $bar->start();

        for ($i = 0; $i < $toCreate; $i++) {
            $index = $existing + $i + 1;
            $firstName = $firstNames[$index % count($firstNames)];
            $lastName = $lastNames[intdiv($index, count($firstNames)) % count($lastNames)];
            $email = "{$prefix}.customer{$index}@demo.kawhe.test";

            $customer = Customer::firstOrCreate(
                ['email' => $email],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'name' => "{$firstName} {$lastName}",
                    'phone' => '+6421'.str_pad((string) (200000 + $index), 7, '0', STR_PAD_LEFT),
                    'birthday' => now()->subYears(20 + ($index % 25))->subDays($index)->toDateString(),
                    'email_verified_at' => now()->subDays(rand(1, 60)),
                ]
            );

            $totalStamps = match ($index % 5) {
                0 => $target * 2,
                1 => $target - 1,
                2 => rand(1, max(1, $target - 2)),
                3 => 0,
                default => $target,
            };

            $rewardBalance = intdiv($totalStamps, max(1, $target));
            $stampCount = $totalStamps % max(1, $target);
            $joinedAt = now()->subDays(rand(1, 45))->subHours(rand(0, 23));
            $lastStampedAt = $totalStamps > 0 ? now()->subDays(rand(0, 14)) : null;

            $account = LoyaltyAccount::create([
                'store_id' => $store->id,
                'loyalty_program_id' => $program->id,
                'customer_id' => $customer->id,
                'stamp_count' => $stampCount,
                'reward_balance' => $rewardBalance,
                'last_stamped_at' => $lastStampedAt,
                'reward_available_at' => $rewardBalance > 0 ? now()->subDays(rand(1, 7)) : null,
                'verified_at' => now()->subDays(rand(1, 30)),
                'created_at' => $joinedAt,
                'updated_at' => $joinedAt,
            ]);

            $this->seedActivity($merchant, $store, $account, $totalStamps, $rewardBalance, $joinedAt);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function seedActivity(User $merchant, Store $store, LoyaltyAccount $account, int $totalStamps, int $rewardBalance, $joinedAt): void
    {
        if ($totalStamps <= 0) {
            return;
        }

        $remaining = $totalStamps;
        $eventIndex = 0;

        while ($remaining > 0) {
            $batch = min($remaining, rand(1, 3));
            $remaining -= $batch;
            $eventIndex++;

            StampEvent::create([
                'loyalty_account_id' => $account->id,
                'store_id' => $store->id,
                'user_id' => $merchant->id,
                'type' => 'stamp',
                'count' => $batch,
                'idempotency_key' => "demo-{$account->id}-stamp-{$eventIndex}",
                'created_at' => $joinedAt->copy()->addDays($eventIndex),
                'updated_at' => $joinedAt->copy()->addDays($eventIndex),
            ]);
        }

        if ($rewardBalance > 0) {
            PointsTransaction::create([
                'loyalty_account_id' => $account->id,
                'store_id' => $store->id,
                'user_id' => $merchant->id,
                'type' => 'earn',
                'points' => $rewardBalance * max(1, (int) $account->reward_target),
                'idempotency_key' => "demo-{$account->id}-earn",
                'metadata' => ['newly_earned_rewards' => $rewardBalance],
                'created_at' => $joinedAt->copy()->addDays($eventIndex + 1),
                'updated_at' => $joinedAt->copy()->addDays($eventIndex + 1),
            ]);

            if ($eventIndex % 2 === 0) {
                StampEvent::create([
                    'loyalty_account_id' => $account->id,
                    'store_id' => $store->id,
                    'user_id' => $merchant->id,
                    'type' => 'redeem',
                    'count' => 1,
                    'idempotency_key' => "demo-{$account->id}-redeem",
                    'created_at' => $joinedAt->copy()->addDays($eventIndex + 2),
                    'updated_at' => $joinedAt->copy()->addDays($eventIndex + 2),
                ]);

                PointsTransaction::create([
                    'loyalty_account_id' => $account->id,
                    'store_id' => $store->id,
                    'user_id' => $merchant->id,
                    'type' => 'redeem',
                    'points' => -1 * max(1, (int) $account->reward_target),
                    'idempotency_key' => "demo-{$account->id}-redeem-tx",
                    'metadata' => ['rewards_redeemed' => 1],
                    'created_at' => $joinedAt->copy()->addDays($eventIndex + 2),
                    'updated_at' => $joinedAt->copy()->addDays($eventIndex + 2),
                ]);
            }
        }
    }
}
