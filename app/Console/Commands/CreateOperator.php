<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateOperator extends Command
{
    protected $signature = 'operator:create {email} {name=Operator}
        {--rotate : Issue a new link, invalidating the old one}
        {--admin : Grant this operator admin rights (can see/end any session)}
        {--revoke-admin : Remove admin rights from this operator}';

    protected $description = 'Create an operator (or print/rotate their personal access link)';

    public function handle(): int
    {
        $email = $this->argument('email');
        $name = $this->argument('name');

        $validator = Validator::make(['email' => $email], ['email' => ['required', 'email']]);

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first());

            return self::FAILURE;
        }

        // Never overwrites the name of an existing operator — only creates.
        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make(Str::random(40))]
        );

        $isNew = $user->wasRecentlyCreated;

        if (! $user->access_token || $this->option('rotate')) {
            $user->access_token = Str::random(40);
            $user->save();
        }

        if ($this->option('admin')) {
            $user->forceFill(['is_admin' => true])->save();
        } elseif ($this->option('revoke-admin')) {
            $user->forceFill(['is_admin' => false])->save();
        }

        $this->components->info(
            $isNew ? "Created operator: {$email}" : "Operator already exists: {$email}"
        );

        if ($user->is_admin) {
            $this->line('Admin: yes');
        }

        $this->newLine();
        $this->line('Personal link (bookmark this — it never expires unless rotated):');
        $this->line(rtrim(config('app.url'), '/').'/op/'.$user->access_token);
        $this->newLine();

        return self::SUCCESS;
    }
}
