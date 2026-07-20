<?php

namespace App\Jobs;

use App\Models\LoyaltyAccount;
use App\Models\LoyaltyProgram;
use App\Services\Support\SupportAuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class RefreshProgramWalletsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 300;

    public function __construct(
        public readonly int $loyaltyProgramId,
        public readonly int $afterAccountId = 0,
    ) {}

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("wallet-program-refresh:{$this->loyaltyProgramId}"))
                ->releaseAfter(20)
                ->expireAfter(180),
        ];
    }

    public function uniqueId(): string
    {
        return "{$this->loyaltyProgramId}:{$this->afterAccountId}";
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(SupportAuditService $supportAuditService): void
    {
        $program = LoyaltyProgram::withTrashed()->find($this->loyaltyProgramId);
        if (! $program || $program->trashed()) {
            return;
        }

        $accounts = LoyaltyAccount::query()
            ->where('loyalty_program_id', $program->id)
            ->where('id', '>', $this->afterAccountId)
            ->orderBy('id')
            ->limit(100)
            ->get(['id']);

        foreach ($accounts as $account) {
            UpdateWalletPassJob::dispatch($account->id);
        }

        if ($accounts->count() === 100) {
            self::dispatch($program->id, (int) $accounts->last()->id);

            return;
        }

        $supportAuditService->log(
            eventType: 'program_wallet_refresh',
            status: 'queued',
            storeId: $program->store_id,
            source: 'system',
            message: 'Wallet refresh jobs were queued for loyalty-card customers.',
            metadata: [
                'program_id' => $program->id,
                'last_chunk_count' => $accounts->count(),
                'last_account_id' => $accounts->last()?->id,
            ]
        );
    }
}
