<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MakeSuperAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kawhe:make-superadmin {email : The email address of the user to promote}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Promote a user to super admin';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');

        $user = \App\Models\User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email '{$email}' not found.");
            return 1;
        }

        if ($user->is_super_admin) {
            $this->info("User '{$email}' is already a super admin.");
            return 0;
        }

        $user->update(['is_super_admin' => true]);

        $this->info("User '{$email}' has been promoted to super admin.");
        return 0;
    }
}
