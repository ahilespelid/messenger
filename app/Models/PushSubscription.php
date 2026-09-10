<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushSubscription extends Model
{
    protected $fillable = [
        'subscriber_type',
        'subscriber_key',
        'endpoint',
        'endpoint_hash',
        'public_key',
        'auth_token',
    ];
}
