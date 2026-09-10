<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamMessage extends Model
{
    protected $fillable = [
        'sender_id',
        'recipient_id',
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

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function isVoice(): bool
    {
        return $this->type === 'voice';
    }

    /**
     * Channel name for a pair of operators — order-independent, so both
     * sides of the DM subscribe to the same presence channel.
     */
    public static function channelFor(int $a, int $b): string
    {
        return 'team.'.min($a, $b).'-'.max($a, $b);
    }

    public function toBroadcastArray(): array
    {
        $deleted = (bool) $this->deleted_at;

        return [
            'id' => $this->id,
            'sender_id' => $this->sender_id,
            'recipient_id' => $this->recipient_id,
            'type' => $this->type,
            'body' => $deleted ? null : ($this->type === 'text' ? $this->body : null),
            'original_body' => $deleted ? null : $this->original_body,
            'voice_duration' => $deleted ? null : $this->voice_duration,
            'read_at' => $this->read_at?->toIso8601String(),
            'edited_at' => $this->edited_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    public function toRevealArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'body' => $this->type === 'text' ? $this->body : null,
            'original_body' => $this->original_body,
            'voice_duration' => $this->voice_duration,
        ];
    }
}
