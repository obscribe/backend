<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdminCommand extends Command
{
    protected $signature = 'obscribe:create-admin';
    protected $description = 'Create an admin user for self-hosted Obscribe';

    public function handle(): int
    {
        $this->info('Create an Obscribe admin account');
        $this->newLine();

        $name = $this->ask('Name');
        $email = $this->ask('Email');
        $password = $this->secret('Password (min 8 characters)');
        $passwordConfirmation = $this->secret('Confirm password');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);

        $this->newLine();
        $this->info("Admin account created! Log in at " . config('app.url') . " to set up your encryption vault.");

        return self::SUCCESS;
    }
}
