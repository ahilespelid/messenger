<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileMessageService
{
    public function store(
        Conversation $conversation,
        string $senderType,
        ?int $userId,
        UploadedFile $file
    ): Message {
        $path = $file->store('file-messages', 'local');

        $message = $conversation->messages()->create([
            'sender_type' => $senderType,
            'user_id' => $userId,
            'type' => 'file',
            'body' => '',
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_mime' => $file->getMimeType() ?: 'application/octet-stream',
            'file_size' => $file->getSize(),
        ]);

        $conversation->forceFill(['last_message_at' => $message->created_at])->save();

        return $message;
    }

    public function stream(Message $message, bool $allowDeleted = false): Response|StreamedResponse
    {
        abort_unless(
            $message->isFile() && ($allowDeleted || ! $message->deleted_at) && $message->file_path
                && Storage::disk('local')->exists($message->file_path),
            404
        );

        // Images/video render inline in the chat; everything else downloads
        // with its original filename.
        $disposition = $message->isImage() || $message->isVideoFile() ? 'inline' : 'attachment';

        return Storage::disk('local')->response(
            $message->file_path,
            $message->file_name,
            ['Content-Type' => $message->file_mime ?: 'application/octet-stream'],
            $disposition
        );
    }
}
