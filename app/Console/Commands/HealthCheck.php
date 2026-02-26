<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class HealthCheck extends Command
{
    protected $signature = 'health:check';

    protected $description = 'Basic production readiness checks (queue, reverb, wallet, stripe webhook)';

    protected bool $hasFailures = false;
    protected bool $hasWarnings = false;

    public function handle(): int
    {
        $this->info('Kawhe Loyalty - Health Check');
        $this->line('');

        $this->checkEnvironment();
        $this->checkLaravelOptimization();
        $this->checkDatabase();
        $this->checkStorage();
        $this->checkQueue();
        $this->checkReverb();
        $this->checkWallet();
        $this->checkStripe();

        $this->line('');
        if ($this->hasFailures) {
            $this->error('Health check failed: fix critical issues before production deploy.');
            return self::FAILURE;
        }

        if ($this->hasWarnings) {
            $this->warn('Health check passed with warnings.');
        } else {
            $this->info('Health check passed.');
        }

        return self::SUCCESS;
    }

    protected function checkEnvironment(): void
    {
        $this->line('Environment:');

        $appEnv = Config::get('app.env');
        $appDebug = (bool) Config::get('app.debug');
        $appUrl = (string) Config::get('app.url');

        $this->line(" - APP_ENV: {$appEnv}");
        $this->line(' - APP_DEBUG: ' . ($appDebug ? '<comment>true</comment>' : 'false'));
        $this->line(" - APP_URL: {$appUrl}");

        if ($appEnv !== 'production') {
            $this->warnResult('APP_ENV is not production');
        }

        if ($appDebug) {
            $this->failResult('APP_DEBUG must be false in production');
        }

        if (! str_starts_with($appUrl, 'https://')) {
            $this->warnResult('APP_URL is not HTTPS');
        }
    }

    protected function checkLaravelOptimization(): void
    {
        $this->line('Laravel optimization:');

        $configCached = app()->configurationIsCached();
        $routesCached = app()->routesAreCached();
        $eventsCached = app()->eventsAreCached();

        $this->line(' - Config cached: ' . ($configCached ? 'yes' : '<comment>no</comment>'));
        $this->line(' - Routes cached: ' . ($routesCached ? 'yes' : '<comment>no</comment>'));
        $this->line(' - Events cached: ' . ($eventsCached ? 'yes' : '<comment>no</comment>'));

        if (! $configCached) {
            $this->warnResult('Config cache is not enabled');
        }
        if (! $routesCached) {
            $this->warnResult('Route cache is not enabled');
        }
        if (! $eventsCached) {
            $this->warnResult('Event cache is not enabled');
        }
    }

    protected function checkDatabase(): void
    {
        $this->line('Database:');
        $connection = Config::get('database.default');
        $this->line(" - Default connection: {$connection}");

        if ($connection === 'sqlite') {
            $this->warnResult('SQLite is configured as default database (use MySQL/PostgreSQL in production)');
        }

        try {
            DB::connection()->getPdo();
            $this->line(' - Connection test: ok');
        } catch (\Throwable $e) {
            $this->failResult('Database connection failed: ' . $e->getMessage());
            return;
        }

        $jobsExists = DB::getSchemaBuilder()->hasTable('jobs');
        $failedJobsExists = DB::getSchemaBuilder()->hasTable('failed_jobs');
        $this->line(' - jobs table: ' . ($jobsExists ? 'exists' : '<comment>missing</comment>'));
        $this->line(' - failed_jobs table: ' . ($failedJobsExists ? 'exists' : '<comment>missing</comment>'));

        if (! $jobsExists) {
            $this->failResult('jobs table is missing (run migrations)');
        }
        if (! $failedJobsExists) {
            $this->warnResult('failed_jobs table is missing');
        }
    }

    protected function checkStorage(): void
    {
        $this->line('Storage and permissions:');

        $storageWritable = File::isWritable(storage_path());
        $cacheWritable = File::isWritable(base_path('bootstrap/cache'));
        $publicStorageExists = is_link(public_path('storage')) || is_dir(public_path('storage'));

        $this->line(' - storage writable: ' . ($storageWritable ? 'yes' : '<comment>no</comment>'));
        $this->line(' - bootstrap/cache writable: ' . ($cacheWritable ? 'yes' : '<comment>no</comment>'));
        $this->line(' - public/storage linked: ' . ($publicStorageExists ? 'yes' : '<comment>no</comment>'));

        if (! $storageWritable) {
            $this->failResult('storage directory is not writable');
        }
        if (! $cacheWritable) {
            $this->failResult('bootstrap/cache is not writable');
        }
        if (! $publicStorageExists) {
            $this->warnResult('public/storage symlink missing (run php artisan storage:link)');
        }
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

            if (! $tableExists) {
                $this->failResult("Queue table `{$table}` is missing");
            }

            if (is_numeric($pending) && $pending > 1000) {
                $this->warnResult("Queue backlog is high ({$pending} pending jobs)");
            }
        } elseif ($driver === 'sync') {
            $this->warnResult('Queue driver is sync (jobs run inline). Consider database/redis for production.');
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
                $this->warnResult('Reverb host/port not configured');
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

        if (! $passType || ! $teamId) {
            $this->warnResult('Apple Wallet pass identifiers are incomplete');
        }
        if (! $googleIssuer) {
            $this->warnResult('Google Wallet issuer id is missing');
        }
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

        if (! $key || ! $priceId) {
            $this->warnResult('Stripe key or price id missing');
        }
        if (! $webhookSecret) {
            $this->warnResult('Stripe webhook secret missing');
        }
    }

    protected function failResult(string $message): void
    {
        $this->hasFailures = true;
        $this->error(' ! ' . $message);
    }

    protected function warnResult(string $message): void
    {
        $this->hasWarnings = true;
        $this->warn(' - ' . $message);
    }
}
