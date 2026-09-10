<?php

namespace App\Auth;

use App\Models\Conversation;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

class ConversationGuestProvider implements UserProvider
{
    public function retrieveById($identifier)
    {
        $conversation = Conversation::where('token', $identifier)->first();

        return $conversation ? new ConversationGuest($conversation) : null;
    }

    public function retrieveByToken($identifier, #[\SensitiveParameter] $token)
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, #[\SensitiveParameter] $token)
    {
        //
    }

    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials)
    {
        return null;
    }

    public function validateCredentials(Authenticatable $user, #[\SensitiveParameter] array $credentials)
    {
        return false;
    }

    public function rehashPasswordIfRequired(Authenticatable $user, #[\SensitiveParameter] array $credentials, bool $force = false)
    {
        //
    }
}
