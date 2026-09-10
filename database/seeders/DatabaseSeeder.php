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
     * Creates (or updates the password of) the single operator account
     * from OPERATOR_EMAIL / OPERATOR_PASSWORD in .env.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('OPERATOR_EMAIL', 'operator@example.com')],
            [
                'name' => env('OPERATOR_NAME', 'Operator'),
                'password' => Hash::make(env('OPERATOR_PASSWORD', 'password')),
            ]
        );
    }
}
