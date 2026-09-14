<?php

namespace App\Http\Middleware;

use App\Models\SessionActivity;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records who is behind each live session — an operator (users.id) or an
 * anonymous client (conversations.id, via the `client` guard) — so an admin
 * can see and end any of them by name. See SessionActivity.
 */
class TrackSessionActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->hasSession()) {
            return $response;
        }

        $sessionId = $request->session()->getId();

        if ($operator = Auth::guard('web')->user()) {
            SessionActivity::track($sessionId, 'operator', $operator->id, $operator->name, $request->ip(), $request->userAgent());
        } elseif ($guest = Auth::guard('client')->user()) {
            SessionActivity::track($sessionId, 'client', $guest->conversation->id, $guest->conversation->client_name, $request->ip(), $request->userAgent());
        }

        return $response;
    }
}
