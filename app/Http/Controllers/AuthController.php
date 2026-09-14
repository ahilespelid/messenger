<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Visiting a personal link *is* logging in — no password, no form.
     * Knowledge of the (unguessable) access token is the credential,
     * exactly like the client's /c/{token} link.
     */
    public function loginViaLink(string $token)
    {
        $user = User::where('access_token', $token)->first();

        if (! $user) {
            return response()->view('operator.no-access', [], 404);
        }

        Auth::login($user, remember: true);

        request()->session()->regenerate();

        return redirect()->route('operator.dashboard');
    }

    public function destroy(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('operator.no-access');
    }
}
