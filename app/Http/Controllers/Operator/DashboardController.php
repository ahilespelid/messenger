<?php

namespace App\Http\Controllers\Operator;

use App\Events\MessageRead;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $conversations = $this->conversationList();

        return view('operator.dashboard', [
            'conversations' => $conversations,
            'activeConversation' => null,
            'messages' => [],
        ]);
    }

    public function show(Conversation $conversation)
    {
        $messages = $conversation->messages()->orderBy('created_at')->get();

        $this->markClientMessagesRead($conversation);

        return view('operator.dashboard', [
            'conversations' => $this->conversationList(),
            'activeConversation' => $conversation,
            'messages' => $messages,
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
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->get();
    }
}
