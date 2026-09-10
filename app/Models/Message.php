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

        return Str::limit($this->body, $limit);
    }

    public function toBroadcastArray(): array
    {
        $deleted = (bool) $this->deleted_at;

        return [
            'id' => $this->id,
            'conversation_token' => $this->conversation->token,
            'sender_type' => $this->sender_type,
            'type' => $this->type,
            'body' => $deleted ? null : ($this->isVoice() ? null : $this->body),
            'original_body' => $deleted ? null : $this->original_body,
            'voice_duration' => $deleted ? null : $this->voice_duration,
            'read_at' => $this->read_at?->toIso8601String(),
            'edited_at' => $this->edited_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
