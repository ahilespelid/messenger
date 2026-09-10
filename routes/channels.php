<?php

use App\Auth\ConversationGuest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// One channel per conversation. The operator authenticates via the normal
// 'web' guard; the anonymous client is authenticated via the lightweight
// 'client' guard (see App\Auth\ConversationGuest) set up when they open
// their /c/{token} link — knowledge of that unguessable token is what
// grants them access, not a real account.
Broadcast::channel('conversation.{token}', function ($user, string $token) {
    $conversation = Conversation::where('token', $token)->first();

    if (! $conversation) {
        return false;
    }

    if ($user instanceof User) {
        return ['id' => 'operator-'.$user->id, 'name' => $user->name, 'role' => 'operator'];
    }

    if ($user instanceof ConversationGuest && $user->conversation->token === $token) {
        return ['id' => 'client-'.$token, 'name' => $conversation->client_name, 'role' => 'client'];
    }

    return false;
}, ['guards' => ['web', 'client']]);

// Only the authenticated operator listens on the inbox channel, which
// receives lightweight "conversation updated" notices for the dashboard list.
Broadcast::channel('operator-inbox', function ($user) {
    return $user instanceof User;
});
