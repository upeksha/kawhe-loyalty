<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LoyaltyAccount;
use App\Models\AppleWalletRegistration;

class CheckWalletRegistration extends Command
{
    protected $signature = 'wallet:check-registration {public_token?}';
    protected $description = 'Check if a loyalty account has device registrations';

    public function handle()
    {
        $publicToken = $this->argument('public_token');
        
        if ($publicToken) {
            $account = LoyaltyAccount::where('public_token', $publicToken)->first();
        } else {
            $account = LoyaltyAccount::latest()->first();
        }
        
        if (!$account) {
            $this->error('No loyalty account found');
            return 1;
        }
        
        $serial = 'kawhe-' . $account->store_id . '-' . $account->customer_id;
        
        $this->info('Account Information:');
        $this->line("  ID: {$account->id}");
        $this->line("  Store ID: {$account->store_id}");
        $this->line("  Customer ID: {$account->customer_id}");
        $this->line("  Public Token: {$account->public_token}");
        $this->line("  Serial: {$serial}");
        $this->line("  Card URL: " . config('app.url') . "/c/{$account->public_token}");
        $this->newLine();
        
        $registrations = AppleWalletRegistration::where('serial_number', $serial)
            ->where('active', true)
            ->get();
        
        $this->info('Device Registrations:');
        if ($registrations->count() > 0) {
            $this->line("  ✓ Found {$registrations->count()} active registration(s)");
            $this->newLine();
            
            foreach ($registrations as $reg) {
                $this->line("  Registration ID: {$reg->id}");
                $this->line("  Device ID: {$reg->device_library_identifier}");
                $this->line("  Push Token: " . substr($reg->push_token, 0, 30) . "...");
                $this->line("  Registered: {$reg->last_registered_at}");
                $this->newLine();
            }
        } else {
            $this->warn("  ⚠️  No active registrations found");
            $this->line("  Add the pass to Apple Wallet first, then wait 10-20 seconds");
            $this->newLine();
        }
        
        // Check for inactive registrations
        $inactive = AppleWalletRegistration::where('serial_number', $serial)
            ->where('active', false)
            ->count();
        
        if ($inactive > 0) {
            $this->line("  Note: {$inactive} inactive registration(s) found");
        }
        
        return 0;
    }
}
