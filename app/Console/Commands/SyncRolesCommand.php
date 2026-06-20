<?php

namespace App\Console\Commands;

use App\Models\User;
use BSPDX\Keystone\Models\KeystoneRole as Role;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SyncRolesCommand extends Command
{
    protected $signature = 'user:sync-roles {email : User email address} {roles* : Role names to assign}';

    protected $description = 'Replace all user roles with the specified roles';

    public function handle(): int
    {
        $email = $this->argument('email');
        $roleNames = $this->argument('roles');

        // Find user
        try {
            $user = User::where('email', $email)->firstOrFail();
        } catch (ModelNotFoundException $e) {
            $this->error("User with email '{$email}' not found.");

            return 1;
        }

        // Validate all roles exist
        $roles = collect();
        foreach ($roleNames as $roleName) {
            try {
                $roles->push(Role::where('name', $roleName)->firstOrFail());
            } catch (ModelNotFoundException $e) {
                $this->error("Role '{$roleName}' does not exist.");
                $availableRoles = Role::pluck('name')->toArray();
                $this->info('Available roles: '.implode(', ', $availableRoles));

                return 1;
            }
        }

        // Show before/after
        $currentRoles = $user->getRoleNames()->join(', ') ?: '<none>';
        $newRoles = $roles->pluck('name')->join(', ') ?: '<none>';

        $this->line("Current roles: {$currentRoles}");
        $this->line("New roles: {$newRoles}");

        $confirm = $this->confirm('Replace all roles with the new set?', true);
        if (! $confirm) {
            $this->info('Operation cancelled.');

            return 0;
        }

        // Sync roles
        $user->syncRoles($roles);

        $this->info("Roles synced successfully for user '{$email}'.");
        $this->line('Current roles: '.$user->getRoleNames()->join(', '));

        return 0;
    }
}
