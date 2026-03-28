<?php

namespace App\Services\Support;

use App\Models\SupportAuditLog;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

class SupportAuditService
{
    public function log(
        string $eventType,
        string $status = 'success',
        ?int $storeId = null,
        ?int $loyaltyAccountId = null,
        ?int $actorUserId = null,
        ?string $source = null,
        ?string $message = null,
        array $metadata = []
    ): void {
        if (!Schema::hasTable('support_audit_logs')) {
            return;
        }

        SupportAuditLog::create([
            'actor_user_id' => $actorUserId,
            'store_id' => $storeId,
            'loyalty_account_id' => $loyaltyAccountId,
            'event_type' => $eventType,
            'status' => $status,
            'source' => $source,
            'message' => $message,
            'metadata' => $metadata ?: null,
        ]);
    }
}
