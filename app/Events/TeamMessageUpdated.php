<?php

namespace App\Events;

use App\Models\TeamMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TeamMessageUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public TeamMessage $message)
    {
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PresenceChannel(TeamMessage::channelFor($this->message->sender_id, $this->message->recipient_id))];
    }

    public function broadcastAs(): string
    {
        return 'team-message.updated';
    }

    public function broadcastWith(): array
    {
        return $this->message->toBroadcastArray();
    }
}
