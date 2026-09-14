<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SessionActivity extends Model
{
    protected $table = 'session_activity';

    public $incrementing = false;

    protected $primaryKey = 'session_id';

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'kind',
        'subject_id',
        'name',
        'ip_address',
        'user_agent',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public static function track(string $sessionId, string $kind, int $subjectId, string $name, ?string $ip, ?string $userAgent): void
    {
        static::updateOrCreate(
            ['session_id' => $sessionId],
            [
                'kind' => $kind,
                'subject_id' => $subjectId,
                'name' => $name,
                'ip_address' => $ip,
                'user_agent' => $userAgent ? str($userAgent)->limit(250)->value() : null,
                'last_seen_at' => now(),
            ]
        );
    }

    /**
     * End a session: dropping the row from Laravel's own `sessions` table
     * invalidates it immediately (their next request finds no session and
     * is treated as a fresh guest), regardless of driver-level caching.
     */
    public static function kill(string $sessionId): void
    {
        DB::table('sessions')->where('id', $sessionId)->delete();
        static::where('session_id', $sessionId)->delete();
    }
}
