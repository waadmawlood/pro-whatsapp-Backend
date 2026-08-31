<?php

namespace App\Notifications;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class NewMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Conversation $conversation,
        public Message $message,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_message',
            'conversation_id' => $this->conversation->id,
            'message_id' => $this->message->id,
            'customer_id' => $this->conversation->customer_id,
            'customer_name' => $this->conversation->customer?->displayName(),
            'preview' => $this->message->preview(),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
