<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('uniqbid:ensure-admin {--reset-password : Reset the admin password from config}')]
#[Description('Ensure the UniqBid admin user exists for staging and production login')]
class EnsureAdminUserCommand extends Command
{
    public function handle(): int
    {
        $name = (string) config('uniqbid.admin.name');
        $email = (string) config('uniqbid.admin.email');
        $password = (string) config('uniqbid.admin.password');

        if ($email === '' || $password === '') {
            $this->error('Configure uniqbid.admin.email and uniqbid.admin.password before running this command.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $user = new User;
            $user->forceFill([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'is_admin' => true,
                'email_verified_at' => now(),
            ])->save();

            $this->info("Created admin user [{$email}].");

            return self::SUCCESS;
        }

        $payload = [
            'name' => $name,
            'is_admin' => true,
        ];

        if ($user->email_verified_at === null) {
            $payload['email_verified_at'] = now();
        }

        if ($this->option('reset-password')) {
            $payload['password'] = $password;
        }

        $user->forceFill($payload)->save();

        $this->info(
            $this->option('reset-password')
                ? "Updated admin user [{$email}] and reset password."
                : "Admin user [{$email}] is ready."
        );

        return self::SUCCESS;
    }
}
