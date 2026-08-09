<?php

namespace App\Console\Commands;

use App\Models\User;
use BSPDX\Keystone\Models\KeystoneRole as Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MakeUserCommand extends Command
{
    protected $signature = 'user:make {--admin : Assign admin role} {--editor : Assign editor role}';

    protected $description = 'Interactively create a new user with optional role assignment';

    public function handle(): int
    {
        $this->info("=== Create New User ===\n");

        // Collect user details
        $name = $this->ask('Full name');

        $email = $this->ask('Email address');

        // Validate email doesn't exist
        if (User::where('email', $email)->exists()) {
            $this->error("User with email '{$email}' already exists.");

            return 1;
        }

        $password = $this->secret('Password (leave empty to auto-generate)');
        $generatedPassword = false;

        if (empty($password)) {
            $password = Str::random(16);
            $generatedPassword = true;
            $this->info("Generated password: {$password}");
        } else {
            $passwordConfirm = $this->secret('Confirm password');
            if ($password !== $passwordConfirm) {
                $this->error("Passwords don't match!");

                return 1;
            }
        }

        // Email verification
        $verified = $this->confirm('Mark email as verified?', true);

        // Create user
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => $verified ? now() : null,
        ]);

        $this->info("\nUser created successfully!");

        // Role assignment
        $assignRole = false;
        $roleName = null;

        if ($this->option('admin')) {
            $roleName = 'admin';
            $assignRole = true;
        } elseif ($this->option('editor')) {
            $roleName = 'editor';
            $assignRole = true;
        } else {
            $assignRole = $this->confirm('Assign a role to this user?', true);

            if ($assignRole) {
                $availableRoles = Role::pluck('name')->toArray();
                if (empty($availableRoles)) {
                    $this->warn('No roles exist in the system. User created without role.');
                } else {
                    $defaultIndex = array_search('user', $availableRoles);
                    $roleName = $this->choice(
                        'Select a role',
                        $availableRoles,
                        $defaultIndex !== false ? $defaultIndex : 0
                    );
                }
            }
        }

        if ($assignRole && $roleName) {
            try {
                $role = Role::where('name', $roleName)->firstOrFail();
                $user->assignRole($role);
                $this->info("Role '{$roleName}' assigned.");
            } catch (\Exception $e) {
                $this->error('Failed to assign role: '.$e->getMessage());
            }
        }

        // Summary
        $this->newLine();
        $this->info('=== User Summary ===');
        $this->line("Name: {$user->name}");
        $this->line("Email: {$user->email}");
        if ($generatedPassword) {
            $this->line("Password: {$password} (auto-generated)");
            $this->warn("Make sure to save this password - it won't be shown again!");
        }
        $this->line('Email Verified: '.($verified ? 'Yes' : 'No'));
        $this->line('Roles: '.($user->getRoleNames()->join(', ') ?: '<none>'));

        return 0;
    }
}
