<?php

namespace App\Console\Commands;

use App\Models\User;
use BSPDX\Keystone\Models\KeystoneRole as Role;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class RemoveRoleCommand extends Command
{
    protected $signature = 'user:remove-role {email? : User email address} {role? : Role name}';

    protected $description = 'Remove a role from a user';

    public function handle(): int
    {
        $email = $this->argument('email');
        $roleName = $this->argument('role');

        // Interactive mode - get email
        if (! $email) {
            $email = $this->ask('User email address');
        }

        // Find user
        try {
            $user = User::where('email', $email)->firstOrFail();
        } catch (ModelNotFoundException $e) {
            $this->error("User with email '{$email}' not found.");

            return 1;
        }

        // Handle role selection
        if (! $roleName) {
            // No role provided - show user's roles
            $userRoles = $user->getRoleNames()->toArray();
            if (empty($userRoles)) {
                $this->warn('User has no roles to remove.');

                return 0;
            }
            $this->info("User's current roles: ".implode(', ', $userRoles));
            $roleName = $this->choice('Select a role to remove', $userRoles);
        } else {
            // Role provided - validate user has it
            if (! $user->hasRole($roleName)) {
                // User doesn't have the role - show their actual roles
                $userRoles = $user->getRoleNames()->toArray();
                if (empty($userRoles)) {
                    $this->warn('User has no roles to remove.');

                    return 0;
                }
                $this->warn("User doesn't have the '{$roleName}' role.");
                $this->info("User's current roles: ".implode(', ', $userRoles));
                $roleName = $this->choice('Select a role to remove', $userRoles);
            }
        }

        // Confirm if super-admin
        if ($roleName === 'super-admin') {
            $confirm = $this->confirm("Warning: You are removing the 'super-admin' role. This will remove all permissions. Continue?", false);
            if (! $confirm) {
                $this->info('Operation cancelled.');

                return 0;
            }
        }

        // Remove role
        $user->removeRole($roleName);

        $this->info("Role '{$roleName}' removed from user '{$email}' successfully.");
        $remainingRoles = $user->getRoleNames();
        if ($remainingRoles->isEmpty()) {
            $this->warn('User now has no roles assigned.');
        } else {
            $this->line('Remaining roles: '.$remainingRoles->join(', '));
        }

        return 0;
    }
}
