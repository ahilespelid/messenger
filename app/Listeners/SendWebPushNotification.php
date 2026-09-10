<?php

namespace App\Listeners;

use App\Events\MessageCreated;
use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;

class SendWebPushNotification implements ShouldQueue
{
    public function __construct(protected WebPushService $webPush)
    {
    }

    public function handle(MessageCreated $event): void
    {
        $message = $event->message;
        $conversation = $message->conversation;

        if ($message->sender_type === 'client') {
            $subscriptions = PushSubscription::where('subscriber_type', 'operator')->get();
            $title = $conversation->client_name;
            $url = '/operator/conversations/'.$conversation->token;
        } else {
            $subscriptions = PushSubscription::where('subscriber_type', 'client')
                ->where('subscriber_key', $conversation->token)
                ->get();
            $title = 'New message';
            $url = '/c/'.$conversation->token;
        }

        if ($subscriptions->isEmpty()) {
            return;
        }

        $this->webPush->sendToMany($subscriptions, $title, Str::limit($message->body, 120), $url);
    }
}
