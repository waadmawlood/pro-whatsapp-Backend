<?php

namespace App\Events;

use App\Http\Resources\MessageResource;
use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message)
    {
        $this->message->loadMissing(['conversation']);
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $conversation = $this->message->conversation;

        $channels = [
            new PrivateChannel('company.'.$conversation->company_id),
            new PrivateChannel('conversation.'.$conversation->id),
        ];

        if ($conversation->assigned_user_id) {
            $channels[] = new PrivateChannel('user.'.$conversation->assigned_user_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'message.status';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message' => (new MessageResource($this->message))->resolve(),
        ];
    }
}
