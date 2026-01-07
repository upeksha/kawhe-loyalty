<?php

namespace App\Events;

use App\Models\LoyaltyAccount;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StampUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public LoyaltyAccount $loyaltyAccount)
    {
        //
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('loyalty-card.' . $this->loyaltyAccount->public_token),
        ];
    }

    public function broadcastAs(): string
    {
        return 'StampUpdated';
    }

    public function broadcastWith(): array
    {
        // Refresh the account to get latest data and ensure relationships are loaded
        $this->loyaltyAccount->refresh();
        $this->loyaltyAccount->load(['store', 'customer']);
        
        return [
            'stamp_count' => $this->loyaltyAccount->stamp_count,
            'reward_target' => $this->loyaltyAccount->store->reward_target,
            'reward_available_at' => $this->loyaltyAccount->reward_available_at?->toIso8601String(),
            'reward_redeemed_at' => $this->loyaltyAccount->reward_redeemed_at?->toIso8601String(),
            'redeem_token' => $this->loyaltyAccount->redeem_token,
            'public_token' => $this->loyaltyAccount->public_token,
            'store_name' => $this->loyaltyAccount->store->name,
            'reward_title' => $this->loyaltyAccount->store->reward_title,
            'customer_name' => $this->loyaltyAccount->customer->name ?? 'Valued Customer',
        ];
    }
}
