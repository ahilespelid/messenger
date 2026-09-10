<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender_type',
        'user_id',
        'type',
        'body',
        'original_body',
        'voice_path',
        'voice_mime',
        'voice_duration',
        'file_path',
        'file_name',
        'file_mime',
        'file_size',
        'read_at',
        'edited_at',
        'deleted_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'edited_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function isVoice(): bool
    {
        return $this->type === 'voice';
    }

    public function isFile(): bool
    {
        return $this->type === 'file';
    }

    public function isImage(): bool
    {
        return $this->isFile() && str_starts_with((string) $this->file_mime, 'image/');
    }

    public function isVideoFile(): bool
    {
        return $this->isFile() && str_starts_with((string) $this->file_mime, 'video/');
    }

    /**
     * A short, human label used where the real content can't be shown
     * (push notification body, conversation list preview).
     */
    public function previewText(int $limit = 80): string
    {
        if ($this->deleted_at) {
            return 'Сообщение удалено';
        }

        if ($this->isVoice()) {
            return '🎤 Голосовое сообщение';
        }

        if ($this->isImage()) {
            return '🖼️ Фото';
        }

        if ($this->isVideoFile()) {
            return '🎬 Видео';
        }

        if ($this->isFile()) {
            return '📎 '.($this->file_name ?: 'Файл');
        }

        return Str::limit($this->body, $limit);
    }

    public function toBroadcastArray(): array
    {
        $deleted = (bool) $this->deleted_at;
        $isTextVisible = ! $deleted && $this->type === 'text';

        return [
            'id' => $this->id,
            'conversation_token' => $this->conversation->token,
            'sender_type' => $this->sender_type,
            'type' => $this->type,
            'body' => $isTextVisible ? $this->body : null,
            'original_body' => $deleted ? null : $this->original_body,
            'voice_duration' => $deleted ? null : $this->voice_duration,
            'file_name' => $deleted ? null : $this->file_name,
            'file_mime' => $deleted ? null : $this->file_mime,
            'file_size' => $deleted ? null : $this->file_size,
            'read_at' => $this->read_at?->toIso8601String(),
            'edited_at' => $this->edited_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    /**
     * Full content regardless of deleted_at — deleting a message never
     * erases it from the database, only hides it from the normal view.
     * Used solely by the operator-only "reveal" endpoint; never broadcast
     * and never sent to the anonymous client guard.
     */
    public function toRevealArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'body' => $this->type === 'text' ? $this->body : null,
            'original_body' => $this->original_body,
            'voice_duration' => $this->voice_duration,
            'file_name' => $this->file_name,
            'file_mime' => $this->file_mime,
            'file_size' => $this->file_size,
        ];
    }
}
