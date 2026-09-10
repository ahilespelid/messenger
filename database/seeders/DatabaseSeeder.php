<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Creates the initial operator account from OPERATOR_EMAIL /
     * OPERATOR_PASSWORD in .env — once. This runs on every container boot
     * (via entrypoint.sh), so it must never touch a row that already
     * exists: an operator renamed via `operator:create`, or anyone who
     * changed their own password, would otherwise get silently reverted
     * back to the .env defaults on the next deploy/restart.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => env('OPERATOR_EMAIL', 'operator@example.com')],
            [
                'name' => env('OPERATOR_NAME', 'Operator'),
                'password' => Hash::make(env('OPERATOR_PASSWORD', 'password')),
            ]
        );
    }
}
