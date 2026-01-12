<?php

namespace App\Console\Commands;

use App\Mail\VerifyCustomerEmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class MailTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kawhe:mail-test {email : The email address to send a test email to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test verification email to verify mail configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Invalid email address: {$email}");
            return 1;
        }

        $this->info("Testing mail configuration...");
        $this->info("Mail Driver: " . config('mail.default'));
        $this->info("Queue Driver: " . config('queue.default'));
        $this->info("APP_URL: " . config('app.url'));

        // Create a test mailable
        $testToken = Str::random(40);
        $testPublicToken = 'test-' . Str::random(40);
        $mailable = new VerifyCustomerEmail($testToken, $testPublicToken);

        try {
            $this->info("\nQueuing test email to: {$email}");
            Mail::to($email)->queue($mailable);
            
            $this->info("✓ Email queued successfully!");
            $this->info("\nTo process the queue, run:");
            $this->info("  php artisan queue:work");
            $this->info("\nTo check queue status:");
            $this->info("  php artisan queue:monitor");
            $this->info("\nTo view logs:");
            $this->info("  tail -f storage/logs/laravel.log");
            
            if (config('mail.default') === 'log') {
                $this->warn("\n⚠ Mail driver is set to 'log' - email will be written to storage/logs/laravel.log");
                $this->info("Check the log file to see the email content.");
            }
            
            return 0;
        } catch (\Exception $e) {
            $this->error("✗ Failed to queue email: " . $e->getMessage());
            $this->error("\nError details:");
            $this->error($e->getTraceAsString());
            
            $this->info("\nTroubleshooting:");
            $this->info("1. Check your .env file for MAIL_* settings");
            $this->info("2. Ensure queue tables are migrated: php artisan migrate");
            $this->info("3. Check storage/logs/laravel.log for detailed errors");
            
            return 1;
        }
    }
}
