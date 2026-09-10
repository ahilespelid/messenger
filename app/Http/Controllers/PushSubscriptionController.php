<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PushSubscriptionController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'token' => ['nullable', 'string'],
        ]);

        if (Auth::check()) {
            $type = 'operator';
            $key = (string) Auth::id();
        } else {
            $conversation = Conversation::where('token', $data['token'] ?? null)->firstOrFail();
            $type = 'client';
            $key = $conversation->token;
        }

        PushSubscription::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $data['endpoint'])],
            [
                'subscriber_type' => $type,
                'subscriber_key' => $key,
                'endpoint' => $data['endpoint'],
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
            ]
        );

        return response()->noContent();
    }

    public function destroy(Request $request)
    {
        $data = $request->validate(['endpoint' => ['required', 'string']]);

        PushSubscription::where('endpoint_hash', hash('sha256', $data['endpoint']))->delete();

        return response()->noContent();
    }
}
