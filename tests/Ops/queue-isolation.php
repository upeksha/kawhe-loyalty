<?php

// Real database/worker regression. Hosted mode only permits the testing checkout.
use Illuminate\Queue\CallQueuedClosure;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$hosted = in_array('--hosted', $argv, true);
if ($hosted && (base_path() !== '/var/www/kawhe-testing' || config('app.url') !== 'https://testing.kawhe.shop')) {
    throw new RuntimeException('Hosted probe requires the testing checkout and URL.');
}
if (! $hosted && ! $app->environment('testing')) {
    throw new RuntimeException('Local probe requires APP_ENV=testing.');
}
$root = sys_get_temp_dir().'/kawhe-queue-probe-'.bin2hex(random_bytes(8));
mkdir($root, 0700);
$processes = [];
$ids = [];
$recipient = $hosted ? (getenv('QUEUE_PROBE_RECIPIENT') ?: null) : null;
if ($recipient && ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    throw new RuntimeException('Invalid probe recipient.');
}
try {
    if (! $hosted) {
        touch($root.'/database.sqlite');
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $root.'/database.sqlite', 'queue.default' => 'database']);
        DB::purge('sqlite');
        Schema::create('jobs', function ($table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
        Schema::create('failed_jobs', function ($table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
        // Consume exactly the queue arguments in the deployed Supervisor template.
        $template = in_array('--production-template', $argv, true)
            ? 'ops/supervisor/kawhe-production-workers.conf'
            : 'ops/supervisor/kawhe-testing-workers.conf';
        $ini = parse_ini_file(base_path($template), true, INI_SCANNER_RAW);
        foreach ($ini as $worker) {
            $command = preg_replace('#^/usr/bin/php#', escapeshellarg(PHP_BINARY), $worker['command']);
            $env = array_merge(getenv(), ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $root.'/database.sqlite', 'QUEUE_CONNECTION' => 'database', 'CACHE_STORE' => 'array', 'APP_CONFIG_CACHE' => $root.'/no-config.php']);
            $process = new Symfony\Component\Process\Process(['sh', '-c', 'exec '.$command], base_path(), $env);
            $process->start();
            $processes[] = $process;
        }
    }
    if (config('queue.default') !== 'database') {
        throw new RuntimeException('Probe requires the database queue.');
    }
    $waitFor = function (string $path, int $seconds = 25): void {
        $until = microtime(true) + $seconds;
        while (! is_file($path) && microtime(true) < $until) {
            usleep(100000);
        }
        if (! is_file($path)) {
            throw new RuntimeException('Timed out waiting for '.basename($path));
        }
    };
    foreach (['slow', 'retry', 'failed'] as $scenario) {
        $prefix = $root.'/'.$scenario;
        $wallet = CallQueuedClosure::create(function ($job) use ($prefix, $scenario) {
            if ($scenario === 'retry' && $job->attempts() === 1) {
                file_put_contents($prefix.'.released', (string) microtime(true));
                $job->release(2);

                return;
            }
            file_put_contents($prefix.'.started', (string) microtime(true));
            sleep(8);
            if ($scenario === 'failed') {
                $job->fail(new RuntimeException('Synthetic Wallet queue isolation failure'));
            }
            file_put_contents($prefix.'.finished', (string) microtime(true));
        })->onQueue('default')->name('Kawhe queue isolation '.$scenario);
        $id = Bus::dispatch($wallet);
        $ids[] = $id;
        $waitFor($prefix.'.started');
        $email = CallQueuedClosure::create(function () use ($prefix, $scenario, $recipient) {
            // Render and deliver a real application template to the log transport only.
            $mail = new App\Mail\CustomerCardRecovery('https://testing.kawhe.shop/login', 'Kawhe queue isolation QA');
            $mail->to('queue-probe@example.invalid')->send(Mail::mailer('log'));
            if ($recipient && $scenario === 'slow') {
                Mail::raw('Testing-only delivery check while Wallet work is busy. No action is required.', function ($message) use ($recipient) {
                    $message->to($recipient)->subject('[Kawhe testing] Wallet/email isolation check');
                });
            }
            file_put_contents($prefix.'.email', (string) microtime(true));
        })->onQueue('emails')->name('Kawhe isolated email probe');
        $queued = microtime(true);
        $ids[] = Bus::dispatch($email);
        $waitFor($prefix.'.email');
        $delivered = (float) file_get_contents($prefix.'.email');
        $waitFor($prefix.'.finished');
        $finished = (float) file_get_contents($prefix.'.finished');
        if ($delivered >= $finished || $delivered - $queued >= 5) {
            throw new RuntimeException('Email was blocked by '.$scenario.' Wallet work.');
        }
        if ($scenario === 'retry' && ! is_file($prefix.'.released')) {
            throw new RuntimeException('Retry was not exercised.');
        }
        echo sprintf("PASS %s: email rendered/delivered to log in %.2fs while default worker busy\n", $scenario, $delivered - $queued);
        if ($recipient && $scenario === 'slow') {
            echo "SMTP transport accepted the additional probe; inbox receipt is not verified.\n";
        }
    }
} finally {
    foreach ($processes as $process) {
        $process->stop(3);
    }
    // Only delete probe records identified by their unique random marker.
    if ($ids) {
        DB::table('jobs')->whereIn('id', $ids)->delete();
    }
    DB::table('failed_jobs')->where('payload', 'like', '%'.basename($root).'%')->delete();
    foreach (glob($root.'/*') as $file) {
        unlink($file);
    }
    rmdir($root);
}
