<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Wallet\Apple\ApplePushService;

class TestJwtGeneration extends Command
{
    protected $signature = 'wallet:test-jwt';
    protected $description = 'Test APNs JWT generation';

    public function handle()
    {
        $this->info('Testing APNs JWT Generation');
        $this->newLine();

        // Check configuration
        $this->info('Configuration:');
        $this->line('  Key ID: ' . (config('wallet.apple.apns_key_id') ?: 'Not set'));
        $this->line('  Team ID: ' . (config('wallet.apple.apns_team_id') ?: 'Not set'));
        $this->line('  Key Path: ' . (config('wallet.apple.apns_auth_key_path') ?: 'Not set'));
        $this->newLine();

        // Resolve key path
        $keyPath = config('wallet.apple.apns_auth_key_path');
        if (!str_starts_with($keyPath, '/')) {
            $keyPath = storage_path('app/private/' . ltrim($keyPath, '/'));
        }

        $this->info('Resolved Key Path:');
        $this->line('  ' . $keyPath);
        $this->line('  Exists: ' . (file_exists($keyPath) ? 'Yes' : 'No'));
        $this->line('  Readable: ' . (is_readable($keyPath) ? 'Yes' : 'No'));
        
        if (file_exists($keyPath)) {
            $this->line('  Size: ' . filesize($keyPath) . ' bytes');
            $keyContent = file_get_contents($keyPath);
            $this->line('  Starts with: ' . substr($keyContent, 0, 30) . '...');
        }
        $this->newLine();

        // Try to generate JWT using reflection
        try {
            $service = app(ApplePushService::class);
            $reflection = new \ReflectionClass($service);
            $method = $reflection->getMethod('generateJWT');
            $method->setAccessible(true);
            
            $this->info('Generating JWT...');
            $jwt = $method->invoke($service);
            
            $this->info('✅ JWT generated successfully!');
            $this->newLine();
            
            // Parse JWT to show parts
            $parts = explode('.', $jwt);
            if (count($parts) === 3) {
                $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/') . str_repeat('=', (4 - strlen($parts[0]) % 4) % 4)), true);
                $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/') . str_repeat('=', (4 - strlen($parts[1]) % 4) % 4)), true);
                
                $this->info('JWT Header:');
                $this->line('  ' . json_encode($header, JSON_PRETTY_PRINT));
                $this->newLine();
                
                $this->info('JWT Payload:');
                $this->line('  ' . json_encode($payload, JSON_PRETTY_PRINT));
                $this->newLine();
                
                $this->info('JWT Preview:');
                $this->line('  ' . substr($jwt, 0, 50) . '...' . substr($jwt, -20));
                $this->newLine();
                
                // Verify claims
                if (isset($header['alg']) && $header['alg'] === 'ES256') {
                    $this->line('  ✓ Algorithm: ES256');
                } else {
                    $this->error('  ✗ Algorithm should be ES256');
                }
                
                if (isset($header['kid']) && $header['kid'] === config('wallet.apple.apns_key_id')) {
                    $this->line('  ✓ Key ID matches');
                } else {
                    $this->error('  ✗ Key ID mismatch');
                }
                
                if (isset($payload['iss']) && $payload['iss'] === config('wallet.apple.apns_team_id')) {
                    $this->line('  ✓ Team ID (iss) matches');
                } else {
                    $this->error('  ✗ Team ID mismatch');
                }
                
                if (isset($payload['iat'])) {
                    $age = time() - $payload['iat'];
                    $this->line("  ✓ Issued at: {$payload['iat']} (age: {$age}s)");
                }
            }
            
        } catch (\Exception $e) {
            $this->error('❌ JWT generation failed: ' . $e->getMessage());
            $this->newLine();
            $this->line('Stack trace:');
            $this->line($e->getTraceAsString());
            return 1;
        }

        return 0;
    }
}
