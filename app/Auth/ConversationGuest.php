<?php

namespace App\Auth;

use App\Models\Conversation;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A lightweight "identity" for an anonymous client, so their browser session
 * can pass Laravel's private/presence broadcasting auth check without a
 * real user account — knowledge of the conversation token is the credential.
 */
class ConversationGuest implements Authenticatable
{
    public function __construct(public Conversation $conversation)
    {
    }

    public function getAuthIdentifierName(): string
    {
        return 'token';
    }

    public function getAuthIdentifier(): string
    {
        return $this->conversation->token;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
        //
    }

    public function getRememberTokenName(): string
    {
        return '';
    }
}
