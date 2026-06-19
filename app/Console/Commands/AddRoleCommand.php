<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use BSPDX\Keystone\Models\KeystoneRole as Role;

class AddRoleCommand extends Command
{
    protected $signature = 'user:add-role {email? : User email address} {role? : Role name}';

    protected $description = 'Assign a role to a user';

    public function handle(): int
    {
        $email = $this->argument('email');
        $roleName = $this->argument('role');

        // Interactive mode - get email
        if (!$email) {
            $email = $this->ask('User email address');
        }

        // Find user
        try {
            $user = User::where('email', $email)->firstOrFail();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->error("User with email '{$email}' not found.");
            return 1;
        }

        // Handle role selection
        if (!$roleName) {
            // No role provided - show choices from database
            $availableRoles = Role::pluck('name')->toArray();
            if (empty($availableRoles)) {
                $this->error('No roles exist in the system.');
                return 1;
            }
            $this->info("Available roles: " . implode(', ', $availableRoles));
            $roleName = $this->choice('Select a role', $availableRoles);
        } else {
            // Role provided - validate it exists
            try {
                Role::where('name', $roleName)->firstOrFail();
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                // Invalid role - show choices from database
                $this->error("Role '{$roleName}' does not exist.");
                $availableRoles = Role::pluck('name')->toArray();
                if (empty($availableRoles)) {
                    $this->error('No roles exist in the system.');
                    return 1;
                }
                $this->info("Available roles: " . implode(', ', $availableRoles));
                $roleName = $this->choice('Select a role', $availableRoles);
            }
        }

        // Check if already assigned
        if ($user->hasRole($roleName)) {
            $this->warn("User '{$email}' already has the '{$roleName}' role.");
            return 0;
        }

        // Assign role
        $user->assignRole($roleName);

        $this->info("Role '{$roleName}' assigned to user '{$email}' successfully.");
        $this->line("Current roles: " . $user->getRoleNames()->join(', '));

        return 0;
    }
}
