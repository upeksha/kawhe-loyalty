<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateStoreAndCards extends Command
{
    protected $signature = 'kawhe:create-store-and-cards {email : The shop owner email} {store_name : Store name} {count=5 : Number of cards to create}';
    protected $description = 'Create a new store and example loyalty cards for a shop owner';

    public function handle()
    {
        $email = $this->argument('email');
        $storeName = $this->argument('store_name');
        $count = (int) $this->argument('count');

        // Find the user
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email '{$email}' not found.");
            return 1;
        }

        $this->info("Found user: {$user->name} ({$user->email})");

        // Create the store using the relationship
        $store = $user->stores()->create([
            'name' => $storeName,
            'reward_target' => 9,
            'reward_title' => 'Free Coffee',
        ]);

        $this->info("✓ Created store: {$store->name} (ID: {$store->id}, Slug: {$store->slug})");

        // Create example customers and loyalty accounts
        $this->info("Creating {$count} example loyalty cards...");
        
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $names = [
            'Alex Johnson', 'Maria Garcia', 'Tom Wilson', 'Emma Brown', 'Chris Lee'
        ];

        for ($i = 0; $i < $count; $i++) {
            // Create customer with example data
            $customer = Customer::create([
                'name' => $names[$i] ?? "Customer " . ($i + 1),
                'email' => 'customer' . ($i + 1) . '@store2.example.com',
                'phone' => '+1' . rand(2000000000, 9999999999),
            ]);

            // Create loyalty account with some random stamp counts for variety
            $stampCount = rand(0, 12); // Random stamps between 0-12
            $rewardTarget = $store->reward_target ?? 9;
            
            // Calculate reward balance if stamps exceed target
            $rewardBalance = floor($stampCount / $rewardTarget);
            $remainingStamps = $stampCount % $rewardTarget;

            $loyaltyAccount = LoyaltyAccount::create([
                'store_id' => $store->id,
                'customer_id' => $customer->id,
                'stamp_count' => $remainingStamps,
                'reward_balance' => $rewardBalance,
                'public_token' => Str::random(40),
                'version' => 1,
                'last_stamped_at' => $stampCount > 0 ? now()->subDays(rand(1, 30)) : null,
                'reward_available_at' => $rewardBalance > 0 ? now()->subDays(rand(1, 10)) : null,
            ]);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✓ Successfully created {$count} example loyalty cards!");
        $this->info("Store: {$store->name}");
        $this->info("Total cards for this store: " . LoyaltyAccount::where('store_id', $store->id)->count());
        $this->info("Total stores for user: " . $user->stores()->count());
        $this->info("Total cards across all stores: " . LoyaltyAccount::whereIn('store_id', $user->stores()->pluck('id'))->count());

        return 0;
    }
}
