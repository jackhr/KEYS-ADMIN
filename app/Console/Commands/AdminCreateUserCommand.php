<?php

namespace App\Console\Commands;

use App\Models\AdminUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class AdminCreateUserCommand extends Command
{
    protected $signature = 'admin:create-user
        {username : Admin username}
        {password : Admin password}
        {--role=admin : Role value}
        {--inactive : Create the user as inactive}';

    protected $description = 'Create or update an admin user for the admin API.';

    public function handle(): int
    {
        $username = trim((string) $this->argument('username'));
        $password = (string) $this->argument('password');
        $role = trim((string) $this->option('role'));
        $active = ! (bool) $this->option('inactive');

        if ($username === '' || strlen($username) < 2) {
            $this->error('Username must be at least 2 characters.');

            return self::INVALID;
        }

        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');

            return self::INVALID;
        }

        if ($role === '') {
            $role = 'admin';
        }

        $admin = AdminUser::query()->updateOrCreate(
            ['username' => $username],
            [
                'password_hash' => Hash::make($password),
                'role' => $role,
                'active' => $active,
            ]
        );

        $this->info(sprintf(
            'Admin user "%s" is ready (id: %d, role: %s, active: %s).',
            $admin->username,
            $admin->id,
            $admin->role,
            $admin->active ? 'yes' : 'no'
        ));

        return self::SUCCESS;
    }
}
