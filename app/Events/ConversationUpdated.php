<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public Conversation $conversation)
    {
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('operator-inbox')];
    }

    public function broadcastAs(): string
    {
        return 'conversation.updated';
    }

    public function broadcastWith(): array
    {
        $lastMessage = $this->conversation->messages()->latest()->first();

        return [
            'token' => $this->conversation->token,
            'client_name' => $this->conversation->client_name,
            'last_message' => $lastMessage?->previewText(80),
            'last_message_at' => $this->conversation->last_message_at?->toIso8601String(),
            'unread_count' => $this->conversation->unreadCountFor('operator'),
        ];
    }
}
