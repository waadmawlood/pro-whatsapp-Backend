<?php

namespace App\Notifications;

use App\Models\Conversation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ConversationAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Conversation $conversation) {}

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
            'type' => 'conversation_assigned',
            'conversation_id' => $this->conversation->id,
            'customer_id' => $this->conversation->customer_id,
            'customer_name' => $this->conversation->customer?->displayName(),
            'preview' => $this->conversation->last_message_preview,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
