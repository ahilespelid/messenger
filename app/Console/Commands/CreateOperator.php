<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateOperator extends Command
{
    protected $signature = 'operator:create {email} {password} {name=Operator}';

    protected $description = 'Create (or update the password of) an operator account';

    public function handle(): int
    {
        $email = $this->argument('email');
        $password = $this->argument('password');
        $name = $this->argument('name');

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => ['required', 'email'], 'password' => ['required', 'string', 'min:6']]
        );

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first());

            return self::FAILURE;
        }

        $existed = User::where('email', $email)->exists();

        User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make($password)]
        );

        $this->components->info(($existed ? 'Updated' : 'Created')." operator: {$email}");

        return self::SUCCESS;
    }
}
