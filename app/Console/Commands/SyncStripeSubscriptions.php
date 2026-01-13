<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncStripeSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kawhe:sync-subscriptions {user_id? : The user ID to sync (optional, syncs all if not provided)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync subscription status from Stripe for a user or all users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('user_id');
        
        if ($userId) {
            $user = User::find($userId);
            
            if (!$user) {
                $this->error("User with ID {$userId} not found.");
                return 1;
            }
            
            $this->syncUser($user);
        } else {
            // Sync all users with Stripe IDs
            $users = User::whereNotNull('stripe_id')->get();
            
            if ($users->isEmpty()) {
                $this->info("No users with Stripe IDs found.");
                return 0;
            }
            
            $this->info("Syncing subscriptions for {$users->count()} user(s)...");
            
            foreach ($users as $user) {
                $this->syncUser($user);
            }
        }
        
        return 0;
    }
    
    protected function syncUser(User $user)
    {
        $this->info("\nSyncing user: {$user->email} (ID: {$user->id})");
        
        if (!$user->hasStripeId()) {
            $this->warn("  User does not have a Stripe ID. Skipping.");
            return;
        }
        
        try {
            // Sync customer details
            $user->syncStripeCustomerDetails();
            $this->info("  ✓ Synced customer details");
            
            // Sync subscriptions
            $user->syncStripeSubscriptions();
            $this->info("  ✓ Synced subscriptions");
            
            // Check subscription status
            $subscription = $user->subscription('default');
            if ($subscription) {
                $this->info("  ✓ Subscription found: {$subscription->stripe_status}");
            } else {
                $this->warn("  ⚠ No 'default' subscription found");
            }
            
        } catch (\Exception $e) {
            $this->error("  ✗ Error: " . $e->getMessage());
            Log::error('Failed to sync Stripe subscription', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
