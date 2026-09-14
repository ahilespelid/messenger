<?php

namespace App\Http\Controllers\Operator;

use App\Events\MessageRead;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\TeamMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function index()
    {
        return view('operator.dashboard', [
            'conversations' => $this->conversationList(),
            'colleagues' => $this->colleagueList(),
            'activeConversation' => null,
            'messages' => [],
            'isAdmin' => (bool) Auth::user()->is_admin,
        ]);
    }

    public function show(Conversation $conversation)
    {
        $messages = $conversation->messages()->orderBy('created_at')->get();

        $this->markClientMessagesRead($conversation);

        return view('operator.dashboard', [
            'conversations' => $this->conversationList(),
            'colleagues' => $this->colleagueList(),
            'activeConversation' => $conversation,
            'messages' => $messages,
            'isAdmin' => (bool) Auth::user()->is_admin,
        ]);
    }

    public function messagesJson(Conversation $conversation)
    {
        $messages = $conversation->messages()->orderBy('created_at')->get();

        $this->markClientMessagesRead($conversation);

        return response()->json([
            'conversation' => [
                'token' => $conversation->token,
                'client_name' => $conversation->client_name,
                'client_email' => $conversation->client_email,
            ],
            'messages' => $messages->map(fn ($m) => $m->toBroadcastArray()),
        ]);
    }

    public function markRead(Conversation $conversation)
    {
        $this->markClientMessagesRead($conversation);

        return response()->noContent();
    }

    protected function markClientMessagesRead(Conversation $conversation): void
    {
        $updated = $conversation->messages()
            ->where('sender_type', 'client')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($updated) {
            broadcast(new MessageRead($conversation, 'operator'))->toOthers();
        }
    }

    protected function conversationList()
    {
        return Conversation::query()
            ->withCount(['messages as unread_count' => fn ($q) => $q->where('sender_type', 'client')->whereNull('read_at')])
            ->with(['messages' => fn ($q) => $q->latest()->limit(1)])
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Other operators, pinned at the top of the sidebar for internal DMs.
     */
    protected function colleagueList()
    {
        $me = Auth::id();

        return User::where('id', '!=', $me)->orderBy('name')->get()->map(function (User $user) use ($me) {
            $lastMessage = TeamMessage::query()
                ->where(function ($q) use ($me, $user) {
                    $q->where('sender_id', $me)->where('recipient_id', $user->id);
                })
                ->orWhere(function ($q) use ($me, $user) {
                    $q->where('sender_id', $user->id)->where('recipient_id', $me);
                })
                ->latest()
                ->first();

            $unreadCount = TeamMessage::where('sender_id', $user->id)
                ->where('recipient_id', $me)
                ->whereNull('read_at')
                ->count();

            return [
                'id' => $user->id,
                'name' => $user->name,
                'last_message' => $lastMessage
                    ? ($lastMessage->deleted_at ? 'Сообщение удалено' : Str::limit($lastMessage->body, 80))
                    : null,
                'last_message_at' => $lastMessage?->created_at->toIso8601String(),
                'unread_count' => $unreadCount,
            ];
        });
    }
}
