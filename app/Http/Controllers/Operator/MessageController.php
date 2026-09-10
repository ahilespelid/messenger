<?php

namespace App\Http\Controllers\Operator;

use App\Events\ConversationUpdated;
use App\Events\MessageCreated;
use App\Events\MessageUpdated;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\FileMessageService;
use App\Services\VoiceMessageService;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function store(Request $request, Conversation $conversation)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $message = $conversation->messages()->create([
            'sender_type' => 'operator',
            'user_id' => $request->user()->id,
            'type' => 'text',
            'body' => $data['body'],
        ]);

        $conversation->forceFill(['last_message_at' => $message->created_at])->save();

        broadcast(new MessageCreated($message))->toOthers();
        broadcast(new ConversationUpdated($conversation));

        return response()->json(['id' => $message->id, 'created_at' => $message->created_at->toIso8601String()]);
    }

    public function storeVoice(Request $request, Conversation $conversation, VoiceMessageService $voiceMessages)
    {
        $data = $request->validate([
            'audio' => ['required', 'file', 'max:10240'],
            'duration' => ['required', 'integer', 'min:1', 'max:300'],
        ]);

        $message = $voiceMessages->store($conversation, 'operator', $request->user()->id, $data['audio'], $data['duration']);

        broadcast(new MessageCreated($message))->toOthers();
        broadcast(new ConversationUpdated($conversation));

        return response()->json([
            'id' => $message->id,
            'voice_duration' => $message->voice_duration,
            'created_at' => $message->created_at->toIso8601String(),
        ]);
    }

    public function streamVoice(Conversation $conversation, Message $message, VoiceMessageService $voiceMessages)
    {
        abort_unless($message->conversation_id === $conversation->id, 404);

        return $voiceMessages->stream($message);
    }

    public function storeFile(Request $request, Conversation $conversation, FileMessageService $fileMessages)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:25600'],
        ]);

        $message = $fileMessages->store($conversation, 'operator', $request->user()->id, $data['file']);

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

    public function streamFile(Conversation $conversation, Message $message, FileMessageService $fileMessages)
    {
        abort_unless($message->conversation_id === $conversation->id, 404);

        return $fileMessages->stream($message);
    }

    public function update(Request $request, Conversation $conversation, Message $message)
    {
        $this->authorizeOwnMessage($message, $conversation);

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

    public function destroy(Conversation $conversation, Message $message)
    {
        $this->authorizeOwnMessage($message, $conversation);

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

    protected function authorizeOwnMessage(Message $message, Conversation $conversation): void
    {
        abort_unless(
            $message->conversation_id === $conversation->id && $message->sender_type === 'operator',
            403
        );
    }
}
