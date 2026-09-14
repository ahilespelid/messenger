<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Creates the initial operator account from OPERATOR_EMAIL in .env —
     * once. This runs on every container boot (via entrypoint.sh), so it
     * must never touch a row that already exists: an operator renamed via
     * `operator:create` would otherwise get silently reverted back to the
     * .env defaults on the next deploy/restart.
     *
     * There's no password anymore (see AuthController::loginViaLink) — just
     * backfill an access_token if this row doesn't have one yet, using
     * OPERATOR_ACCESS_TOKEN from .env when given, or a fresh random one.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => env('OPERATOR_EMAIL', 'operator@example.com')],
            [
                'name' => env('OPERATOR_NAME', 'Operator'),
                'password' => Hash::make(Str::random(40)),
                'is_admin' => true,
            ]
        );

        if (! $user->access_token) {
            $user->access_token = env('OPERATOR_ACCESS_TOKEN') ?: Str::random(40);
            $user->save();
        }

        // Guarantee there is always at least one admin — relevant when
        // upgrading an install that predates the admin flag.
        if (! User::where('is_admin', true)->exists()) {
            $user->forceFill(['is_admin' => true])->save();
        }
    }
}
