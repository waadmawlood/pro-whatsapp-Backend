<?php

namespace App\Events;

use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Conversation $conversation,
        public string $action = 'updated',
    ) {
        $this->conversation->loadMissing(['customer.tags', 'assignedUser', 'whatsappAccount']);
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('company.'.$this->conversation->company_id),
            new PrivateChannel('conversation.'.$this->conversation->id),
        ];

        if ($this->conversation->assigned_user_id) {
            $channels[] = new PrivateChannel('user.'.$this->conversation->assigned_user_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'conversation.'.$this->action;
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation' => (new ConversationResource($this->conversation))->resolve(),
            'action' => $this->action,
        ];
    }
}
