<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class InviteSuperAdmin extends Command
{
    protected $signature = 'kawhe:invite-superadmin {email} {--resend : Resend setup emails to an existing administrator}';

    protected $description = 'Create a dedicated admin and email password setup and verification links';

    public function handle(): int
    {
        $email = Str::lower(trim($this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('A valid email is required.');

            return self::FAILURE;
        }
        $user = User::where('email', $email)->first();
        if ($user && ! $user->isSuperAdmin()) {
            $this->error('Account already exists. Review it and use kawhe:make-superadmin explicitly.');

            return self::FAILURE;
        }
        if ($user && ! $this->option('resend')) {
            $this->info('Administrator already exists. Use --resend if setup links are needed.');

            return self::SUCCESS;
        }
        $user ??= User::create([
            'name' => 'Kawhe Admin', 'email' => $email,
            'password' => Hash::make(Str::random(64)), 'is_super_admin' => true,
        ]);
        $status = Password::sendResetLink(['email' => $email]);
        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }
        $this->info('Admin created or retained. Password setup status: '.$status.'. Verification remains required.');

        return $status === Password::RESET_LINK_SENT ? self::SUCCESS : self::FAILURE;
    }
}
