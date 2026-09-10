<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'client_name',
        'client_email',
        'status',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function unreadCountFor(string $viewerType): int
    {
        $senderType = $viewerType === 'operator' ? 'client' : 'operator';

        return $this->messages()->where('sender_type', $senderType)->whereNull('read_at')->count();
    }
}
