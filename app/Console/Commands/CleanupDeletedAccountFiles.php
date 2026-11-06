<?php

namespace App\Console\Commands;

use App\Models\AccountFileCleanup;
use App\Services\Billing\AccountFileDeletion;
use Illuminate\Console\Command;

class CleanupDeletedAccountFiles extends Command
{
    protected $signature = 'accounts:cleanup-files {--limit=10 : Maximum deletion requests per run}';

    protected $description = 'Retry file cleanup for permanently deleted accounts; never archive or delete accounts';

    public function handle(AccountFileDeletion $deletion): int
    {
        $failed = false;
        $cleanups = AccountFileCleanup::whereNull('completed_at')->where('available_at', '<=', now())
            ->orderBy('id')->limit(max(1, min(100, (int) $this->option('limit'))))->get();
        foreach ($cleanups as $cleanup) {
            if (! $deletion->process($cleanup)) {
                $failed = true;
                $this->error("File cleanup {$cleanup->id} remains pending; it will retry.");
            } else {
                $this->info("File cleanup {$cleanup->id} completed.");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
