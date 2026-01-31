<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateExampleCards extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kawhe:create-example-cards {email : The shop owner email} {count=40 : Number of cards to create}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create example loyalty cards for a shop owner';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $count = (int) $this->argument('count');

        // Find the user
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email '{$email}' not found.");
            return 1;
        }

        $this->info("Found user: {$user->name} ({$user->email})");

        // Get the first store for this user
        $store = $user->stores()->first();

        if (!$store) {
            $this->error("No store found for user '{$email}'. Please create a store first.");
            return 1;
        }

        $this->info("Using store: {$store->name} (ID: {$store->id})");

        // Create example customers and loyalty accounts
        $this->info("Creating {$count} example loyalty cards...");
        
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $names = [
            'John Smith', 'Sarah Johnson', 'Michael Brown', 'Emily Davis', 'David Wilson',
            'Jessica Martinez', 'Christopher Anderson', 'Amanda Taylor', 'Matthew Thomas', 'Ashley Jackson',
            'Daniel White', 'Jennifer Harris', 'James Martin', 'Lisa Thompson', 'Robert Garcia',
            'Michelle Martinez', 'William Rodriguez', 'Kimberly Lewis', 'Richard Lee', 'Nicole Walker',
            'Joseph Hall', 'Stephanie Allen', 'Thomas Young', 'Angela King', 'Charles Wright',
            'Melissa Lopez', 'Andrew Hill', 'Rebecca Scott', 'Kevin Green', 'Laura Adams',
            'Brian Baker', 'Michelle Gonzalez', 'Steven Nelson', 'Amy Carter', 'Mark Mitchell',
            'Heather Perez', 'Jason Roberts', 'Samantha Turner', 'Ryan Phillips', 'Rachel Campbell'
        ];

        for ($i = 0; $i < $count; $i++) {
            // Create customer with example data
            $customer = Customer::create([
                'name' => $names[$i] ?? "Customer " . ($i + 1),
                'email' => 'customer' . ($i + 1) . '@example.com',
                'phone' => '+1' . rand(2000000000, 9999999999),
            ]);

            // Create loyalty account with some random stamp counts for variety
            $stampCount = rand(0, 15); // Random stamps between 0-15
            $rewardTarget = $store->reward_target ?? 9;
            
            // Calculate reward balance if stamps exceed target
            $rewardBalance = floor($stampCount / $rewardTarget);
            $remainingStamps = $stampCount % $rewardTarget;

            $loyaltyAccount = LoyaltyAccount::create([
                'store_id' => $store->id,
                'customer_id' => $customer->id,
                'stamp_count' => $remainingStamps,
                'reward_balance' => $rewardBalance,
                'public_token' => Str::random(\App\Models\LoyaltyAccount::PUBLIC_TOKEN_LENGTH),
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

        return 0;
    }
}
