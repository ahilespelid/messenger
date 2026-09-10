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

// Internal DM between two operators — presence channel so each side can see
// whether their colleague is online. Only the two participants may join.
Broadcast::channel('team.{pair}', function ($user, string $pair) {
    if (! $user instanceof User) {
        return false;
    }

    $ids = array_map('intval', explode('-', $pair));

    if (count($ids) !== 2 || ! in_array($user->id, $ids, true)) {
        return false;
    }

    return ['id' => 'operator-'.$user->id, 'name' => $user->name, 'role' => 'operator'];
});

// Each operator's own channel, joined regardless of which pane is open, so
// the "pinned colleagues" sidebar can update (unread badge, preview) even
// when that DM thread isn't the one currently on screen.
Broadcast::channel('operator.{id}', function ($user, string $id) {
    return $user instanceof User && (int) $user->id === (int) $id;
});

// A shared presence channel every operator joins on dashboard load, purely
// so the pinned colleagues list can show a live online dot for everyone —
// not just whoever's DM thread happens to be open right now.
Broadcast::channel('operators-online', function ($user) {
    return $user instanceof User ? ['id' => 'operator-'.$user->id, 'name' => $user->name, 'role' => 'operator'] : false;
});
