<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdminCommand extends Command
{
    protected $signature = 'app:create-admin';
    protected $description = 'Create an admin user account';

    public function handle(): int
    {
        $name = $this->ask('Admin name');
        $email = $this->ask('Admin email');
        $password = $this->secret('Admin password');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255'],
                'password' => ['required', 'string', 'min:8'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        // Check if email already exists using blind index
        $existing = User::whereBlind('email', 'email_index', $email)->first();
        if ($existing) {
            $this->error('A user with this email already exists.');
            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => 'admin',
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->info("Admin user '{$name}' created successfully.");

        return self::SUCCESS;
    }
}
