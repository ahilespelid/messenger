<?php

namespace App\Services;

use App\Models\TeamMessage;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeamVoiceMessageService
{
    public function store(int $senderId, int $recipientId, UploadedFile $file, int $duration): TeamMessage
    {
        $path = $file->store('team-voice-messages', 'local');

        return TeamMessage::create([
            'sender_id' => $senderId,
            'recipient_id' => $recipientId,
            'type' => 'voice',
            'body' => '',
            'voice_path' => $path,
            'voice_mime' => $file->getMimeType() ?: 'audio/webm',
            'voice_duration' => $duration,
        ]);
    }

    public function stream(TeamMessage $message, bool $allowDeleted = false): Response|StreamedResponse
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
