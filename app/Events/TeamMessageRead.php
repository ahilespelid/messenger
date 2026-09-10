<?php

namespace App\Events;

use App\Models\TeamMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TeamMessageRead implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public int $senderId, public int $recipientId, public int $readerId)
    {
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PresenceChannel(TeamMessage::channelFor($this->senderId, $this->recipientId))];
    }

    public function broadcastAs(): string
    {
        return 'team-message.read';
    }

    public function broadcastWith(): array
    {
        return [
            'reader_id' => $this->readerId,
            'at' => now()->toIso8601String(),
        ];
    }
}
