<?php

namespace App\Console\Commands;

use App\Models\User;
use BSPDX\Keystone\Models\KeystoneRole as Role;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ListRolesCommand extends Command
{
    protected $signature = 'user:list-roles {email? : User email to show their roles}';

    protected $description = 'List all roles or show a user\'s assigned roles';

    public function handle(): int
    {
        $email = $this->argument('email');

        if ($email) {
            // Show user's roles
            try {
                $user = User::with('roles')->where('email', $email)->firstOrFail();
            } catch (ModelNotFoundException $e) {
                $this->error("User with email '{$email}' not found.");

                return 1;
            }

            $this->info("Roles for {$user->name} ({$email}):");

            if ($user->roles->isEmpty()) {
                $this->warn('No roles assigned.');

                return 0;
            }

            $this->table(
                ['Role', 'Guard', 'Permissions Count'],
                $user->roles->map(function ($role) {
                    return [
                        $role->name,
                        $role->guard_name,
                        $role->permissions->count(),
                    ];
                })
            );

            return 0;
        }

        // Show all system roles
        $roles = Role::with('permissions')->orderBy('name')->get();

        if ($roles->isEmpty()) {
            $this->warn('No roles defined.');

            return 0;
        }

        $this->info('All System Roles:');

        $this->table(
            ['Role', 'Guard', 'Permissions', 'Users'],
            $roles->map(function ($role) {
                return [
                    $role->name,
                    $role->guard_name,
                    $role->permissions->count(),
                    $role->users()->count(),
                ];
            })
        );

        // Show permission details in verbose mode
        $this->newLine();
        if ($this->output->isVerbose()) {
            foreach ($roles as $role) {
                $this->info("Permissions for '{$role->name}':");
                $this->line($role->permissions->pluck('name')->join(', ') ?: '<none>');
                $this->newLine();
            }
        } else {
            $this->comment('Use -v to see detailed permissions for each role');
        }

        return 0;
    }
}
