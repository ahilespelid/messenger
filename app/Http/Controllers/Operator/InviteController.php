<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InviteController extends Controller
{
    /**
     * Any operator can invite another — no admin required. Only session
     * management (SessionController) is admin-gated.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => 'op-'.Str::random(8).'@internal.local',
            'password' => Hash::make(Str::random(40)),
            'access_token' => Str::random(40),
        ]);

        return response()->json([
            'name' => $user->name,
            'link' => rtrim(config('app.url'), '/').'/op/'.$user->access_token,
        ]);
    }
}
