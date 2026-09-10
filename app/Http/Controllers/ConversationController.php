<?php

namespace App\Http\Controllers;

use App\Auth\ConversationGuest;
use App\Events\MessageRead;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ConversationController extends Controller
{
    public function welcome()
    {
        return view('client.welcome');
    }

    public function start(Request $request)
    {
        $data = $request->validate([
            'client_name' => ['required', 'string', 'max:255'],
            'client_email' => ['nullable', 'email', 'max:255'],
        ]);

        $conversation = Conversation::create([
            'token' => Str::random(32),
            'client_name' => $data['client_name'],
            'client_email' => $data['client_email'] ?? null,
        ]);

        return redirect()->route('conversation.show', $conversation->token);
    }

    public function show(string $token)
    {
        $conversation = Conversation::where('token', $token)->first();

        if (! $conversation) {
            // The token in the visitor's localStorage points at a
            // conversation that no longer exists (e.g. after a database
            // reset). A plain 404 would leave them stuck redirecting back
            // to this same dead link forever — send a page that clears it
            // and offers a way to start over instead.
            return response()->view('client.not-found', [], 404);
        }

        $messages = $conversation->messages()->orderBy('created_at')->get();

        Auth::guard('client')->login(new ConversationGuest($conversation));

        $this->markOperatorMessagesRead($conversation);

        return view('client.chat', compact('conversation', 'messages'));
    }

    public function markRead(string $token)
    {
        $conversation = Conversation::where('token', $token)->firstOrFail();

        $this->markOperatorMessagesRead($conversation);

        return response()->noContent();
    }

    protected function markOperatorMessagesRead(Conversation $conversation): void
    {
        $updated = $conversation->messages()
            ->where('sender_type', 'operator')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($updated) {
            broadcast(new MessageRead($conversation, 'client'))->toOthers();
        }
    }
}
