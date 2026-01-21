<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class HealthCheck extends Command
{
    protected $signature = 'health:check';

    protected $description = 'Basic production readiness checks (queue, reverb, wallet, stripe webhook)';

    public function handle(): int
    {
        $this->info('Kawhe Loyalty - Health Check');
        $this->line('');

        $this->checkQueue();
        $this->checkReverb();
        $this->checkWallet();
        $this->checkStripe();

        $this->line('');
        $this->info('Done.');
        return self::SUCCESS;
    }

    protected function checkQueue(): void
    {
        $driver = Config::get('queue.default');
        $this->line("Queue driver: <info>{$driver}</info>");

        if ($driver === 'database') {
            $table = Config::get('queue.connections.database.table', 'jobs');
            $tableExists = DB::getSchemaBuilder()->hasTable($table);
            $pending = $tableExists ? DB::table($table)->count() : 'n/a';
            $this->line(" - Jobs table: " . ($tableExists ? 'exists' : 'missing'));
            $this->line(" - Pending jobs: {$pending}");
        } elseif ($driver === 'sync') {
            $this->warn(' - Queue driver is sync (jobs run inline). Consider database/redis for production.');
        }
    }

    protected function checkReverb(): void
    {
        $driver = Config::get('broadcasting.default');
        $this->line("Broadcast driver: <info>{$driver}</info>");
        if ($driver === 'reverb') {
            $host = Config::get('reverb.host');
            $port = Config::get('reverb.port');
            if (empty($host) || empty($port)) {
                $this->warn(' - Reverb host/port not configured');
            } else {
                $this->line(" - Reverb host: {$host}:{$port}");
            }
        }
    }

    protected function checkWallet(): void
    {
        $passType = Config::get('passgenerator.pass_type_identifier');
        $teamId = Config::get('passgenerator.team_identifier');
        $applePushEnabled = Config::get('wallet.apple.push_enabled');
        $googleIssuer = Config::get('services.google_wallet.issuer_id');

        $this->line('Wallet config:');
        $this->line(' - Apple pass type: ' . ($passType ?: '<comment>missing</comment>'));
        $this->line(' - Apple team id: ' . ($teamId ?: '<comment>missing</comment>'));
        $this->line(' - Apple push enabled: ' . ($applePushEnabled ? 'yes' : 'no'));
        $this->line(' - Google issuer id: ' . ($googleIssuer ?: '<comment>missing</comment>'));
    }

    protected function checkStripe(): void
    {
        $webhookSecret = Config::get('services.stripe.webhook.secret');
        $priceId = Config::get('cashier.price_id');
        $key = Config::get('services.stripe.key');

        $this->line('Stripe config:');
        $this->line(' - Stripe key present: ' . ($key ? 'yes' : '<comment>no</comment>'));
        $this->line(' - Price ID: ' . ($priceId ?: '<comment>missing</comment>'));
        $this->line(' - Webhook secret: ' . ($webhookSecret ? 'set' : '<comment>missing</comment>'));
    }
}
