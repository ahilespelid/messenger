<?php

namespace App\Http\Controllers;

use App\Events\ConversationUpdated;
use App\Events\MessageCreated;
use App\Events\MessageUpdated;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function store(Request $request, string $token)
    {
        $conversation = Conversation::where('token', $token)->firstOrFail();

        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $message = $conversation->messages()->create([
            'sender_type' => 'client',
            'body' => $data['body'],
        ]);

        $conversation->forceFill(['last_message_at' => $message->created_at])->save();

        broadcast(new MessageCreated($message))->toOthers();
        broadcast(new ConversationUpdated($conversation));

        return response()->json(['id' => $message->id, 'created_at' => $message->created_at->toIso8601String()]);
    }

    public function update(Request $request, string $token, Message $message)
    {
        $this->authorizeOwnMessage($message, $token, 'client');

        abort_if($message->deleted_at, 422, 'Message already deleted.');

        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        if (! $message->edited_at) {
            $message->original_body = $message->body;
        }
        $message->body = $data['body'];
        $message->edited_at = now();
        $message->save();

        broadcast(new MessageUpdated($message))->toOthers();

        return response()->json(['edited_at' => $message->edited_at->toIso8601String()]);
    }

    public function destroy(string $token, Message $message)
    {
        $this->authorizeOwnMessage($message, $token, 'client');

        if ($message->deleted_at) {
            return response()->noContent();
        }

        $message->forceFill([
            'deleted_at' => now(),
            'body' => '',
            'original_body' => null,
        ])->save();

        broadcast(new MessageUpdated($message))->toOthers();

        return response()->noContent();
    }

    protected function authorizeOwnMessage(Message $message, string $token, string $senderType): void
    {
        abort_unless(
            $message->conversation->token === $token && $message->sender_type === $senderType,
            403
        );
    }
}
