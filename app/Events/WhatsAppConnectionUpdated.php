<?php

namespace App\Events;

use App\Http\Resources\WhatsAppAccountResource;
use App\Models\WhatsAppAccount;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhatsAppConnectionUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public WhatsAppAccount $account)
    {
        $this->account->makeHidden(['access_token', 'app_secret', 'webhook_verify_token']);
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('company.'.$this->account->company_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'whatsapp.connection';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'account' => (new WhatsAppAccountResource($this->account))->resolve(),
        ];
    }
}
