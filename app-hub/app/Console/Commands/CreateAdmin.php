<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'hub:create-admin {email?} {--name=}';

    protected $description = 'Create an active UHPH App Hub administrator';

    public function handle(): int
    {
        $email = Str::lower(trim((string) ($this->argument('email') ?: $this->ask('Email address'))));
        $name = trim((string) ($this->option('name') ?: $this->ask('Full name')));
        $identity = Validator::make(compact('email', 'name'), [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($identity->fails()) {
            foreach ($identity->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $password = (string) $this->secret('Password');
        $passwordConfirmation = (string) $this->secret('Confirm password');
        $credentials = Validator::make([
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        if ($credentials->fails()) {
            foreach ($credentials->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'status' => User::STATUS_ACTIVE,
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);

        $this->info('Administrator created successfully.');

        return self::SUCCESS;
    }
}
