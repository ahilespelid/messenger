<?php

namespace App\Http\Controllers;

use App\Events\ConversationUpdated;
use App\Events\MessageCreated;
use App\Events\MessageUpdated;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\FileMessageService;
use App\Services\VoiceMessageService;
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
            'type' => 'text',
            'body' => $data['body'],
        ]);

        $conversation->forceFill(['last_message_at' => $message->created_at])->save();

        broadcast(new MessageCreated($message))->toOthers();
        broadcast(new ConversationUpdated($conversation));

        return response()->json(['id' => $message->id, 'created_at' => $message->created_at->toIso8601String()]);
    }

    public function storeVoice(Request $request, string $token, VoiceMessageService $voiceMessages)
    {
        $conversation = Conversation::where('token', $token)->firstOrFail();

        $data = $request->validate([
            'audio' => ['required', 'file', 'max:10240'],
            'duration' => ['required', 'integer', 'min:1', 'max:300'],
        ]);

        $message = $voiceMessages->store($conversation, 'client', null, $data['audio'], $data['duration']);

        broadcast(new MessageCreated($message))->toOthers();
        broadcast(new ConversationUpdated($conversation));

        return response()->json([
            'id' => $message->id,
            'voice_duration' => $message->voice_duration,
            'created_at' => $message->created_at->toIso8601String(),
        ]);
    }

    public function streamVoice(string $token, Message $message, VoiceMessageService $voiceMessages)
    {
        abort_unless($message->conversation->token === $token, 404);

        return $voiceMessages->stream($message);
    }

    public function storeFile(Request $request, string $token, FileMessageService $fileMessages)
    {
        $conversation = Conversation::where('token', $token)->firstOrFail();

        $data = $request->validate([
            'file' => ['required', 'file', 'max:25600'],
        ]);

        $message = $fileMessages->store($conversation, 'client', null, $data['file']);

        broadcast(new MessageCreated($message))->toOthers();
        broadcast(new ConversationUpdated($conversation));

        return response()->json([
            'id' => $message->id,
            'file_name' => $message->file_name,
            'file_mime' => $message->file_mime,
            'file_size' => $message->file_size,
            'created_at' => $message->created_at->toIso8601String(),
        ]);
    }

    public function streamFile(string $token, Message $message, FileMessageService $fileMessages)
    {
        abort_unless($message->conversation->token === $token, 404);

        return $fileMessages->stream($message);
    }

    public function update(Request $request, string $token, Message $message)
    {
        $this->authorizeOwnMessage($message, $token, 'client');

        abort_if($message->deleted_at, 422, 'Message already deleted.');
        abort_if($message->type !== 'text', 422, 'Only text messages can be edited.');

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
