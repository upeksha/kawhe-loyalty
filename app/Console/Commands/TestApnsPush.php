<?php

namespace App\Console\Commands;

use App\Models\LoyaltyAccount;
use App\Services\Wallet\Apple\ApplePushService;
use App\Services\Wallet\Apple\AppleWalletSerial;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestApnsPush extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:apns-test {serialNumber}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test APNs push notification for a given serial number';

    /**
     * Execute the console command.
     */
    public function handle(ApplePushService $pushService): int
    {
        $serialNumber = $this->argument('serialNumber');
        
        $this->info("Testing APNs push for serial number: {$serialNumber}");
        $this->newLine();

        // Resolve account from serial
        $account = AppleWalletSerial::resolveAccount($serialNumber);
        if (!$account) {
            $this->error("No loyalty account found for serial number: {$serialNumber}");
            return 1;
        }

        $this->info("Found account: ID {$account->id}, Store {$account->store_id}, Customer {$account->customer_id}");
        $this->info("Stamp count: {$account->stamp_count}, Reward balance: " . ($account->reward_balance ?? 0));
        $this->newLine();

        // Check registrations
        $registrations = \App\Models\AppleWalletRegistration::where('serial_number', $serialNumber)
            ->where('active', true)
            ->get();

        if ($registrations->isEmpty()) {
            $this->warn("No active registrations found for serial number: {$serialNumber}");
            $this->info("Add the pass to Apple Wallet first to create a registration.");
            return 1;
        }

        $this->info("Found {$registrations->count()} active registration(s)");
        $this->newLine();

        // Get pass type identifier
        $passTypeIdentifier = config('passgenerator.pass_type_identifier');
        $this->info("Pass Type Identifier: {$passTypeIdentifier}");
        $this->newLine();

        // Check APNs configuration
        $this->info("APNs Configuration:");
        $this->line("  Push Enabled: " . (config('wallet.apple.push_enabled') ? 'Yes' : 'No'));
        $this->line("  Key ID: " . (config('wallet.apple.apns_key_id') ?: 'Not set'));
        $this->line("  Team ID: " . (config('wallet.apple.apns_team_id') ?: 'Not set'));
        $this->line("  Topic: " . (config('wallet.apple.apns_topic') ?: 'Not set'));
        $this->line("  Production: " . (config('wallet.apple.apns_production') ? 'Yes' : 'No (Sandbox)'));
        $this->newLine();

        if (!config('wallet.apple.push_enabled')) {
            $this->error("Push notifications are disabled. Set WALLET_APPLE_PUSH_ENABLED=true in .env");
            return 1;
        }

        // Send push
        $this->info("Sending APNs push notification...");
        $this->newLine();

        try {
            $pushService->sendPassUpdatePushes($passTypeIdentifier, $serialNumber);
            
            $this->info("✅ Push notification sent successfully!");
            $this->newLine();
            $this->info("Check logs for detailed APNs response:");
            $this->line("  tail -n 50 storage/logs/laravel.log | grep -i 'push notification'");
            
        } catch (\Exception $e) {
            $this->error("❌ Push notification failed: " . $e->getMessage());
            $this->newLine();
            $this->info("Check logs for full APNs error response:");
            $this->line("  tail -n 50 storage/logs/laravel.log | grep -i 'push notification failed'");
            return 1;
        }

        return 0;
    }
}
