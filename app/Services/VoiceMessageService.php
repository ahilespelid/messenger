<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VoiceMessageService
{
    public function store(
        Conversation $conversation,
        string $senderType,
        ?int $userId,
        UploadedFile $file,
        int $duration
    ): Message {
        $path = $file->store('voice-messages', 'local');

        $message = $conversation->messages()->create([
            'sender_type' => $senderType,
            'user_id' => $userId,
            'type' => 'voice',
            'body' => '',
            'voice_path' => $path,
            'voice_mime' => $file->getMimeType() ?: 'audio/webm',
            'voice_duration' => $duration,
        ]);

        $conversation->forceFill(['last_message_at' => $message->created_at])->save();

        return $message;
    }

    public function stream(Message $message, bool $allowDeleted = false): Response|StreamedResponse
    {
        abort_unless(
            $message->isVoice() && ($allowDeleted || ! $message->deleted_at) && $message->voice_path
                && Storage::disk('local')->exists($message->voice_path),
            404
        );

        return Storage::disk('local')->response(
            $message->voice_path,
            null,
            ['Content-Type' => $message->voice_mime ?: 'audio/webm']
        );
    }
}
