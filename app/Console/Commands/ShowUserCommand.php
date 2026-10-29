<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ShowUserCommand extends Command
{
    protected $signature = 'user:info {email : User email address}';

    protected $description = 'Display detailed information about a user';

    public function handle(): int
    {
        $email = $this->argument('email');

        try {
            $user = User::with(['roles.permissions', 'federatedIdentities'])
                ->where('email', $email)
                ->firstOrFail();
        } catch (ModelNotFoundException $e) {
            $this->error("User with email '{$email}' not found.");

            return 1;
        }

        // Basic info
        $this->info("\n=== User Information ===");
        $this->line("ID: {$user->id}");
        $this->line("Name: {$user->name}");
        $this->line("Email: {$user->email}");
        $this->line('Email Verified: '.($user->email_verified_at ? "Yes ({$user->email_verified_at->format('Y-m-d H:i:s')})" : 'No'));
        $this->line("Created: {$user->created_at->format('Y-m-d H:i:s')}");
        $this->line("Updated: {$user->updated_at->format('Y-m-d H:i:s')}");

        // Roles
        $this->info("\n=== Roles ===");
        if ($user->roles->isEmpty()) {
            $this->warn('No roles assigned');
        } else {
            $this->table(['Role', 'Guard'], $user->roles->map(fn ($role) => [
                $role->name,
                $role->guard_name,
            ]));
        }

        // Permissions (via roles)
        $this->info("\n=== Permissions (via roles) ===");
        $permissions = $user->getAllPermissions();
        if ($permissions->isEmpty()) {
            $this->warn('No permissions');
        } else {
            $this->line($permissions->pluck('name')->sort()->join(', '));
        }

        // Direct permissions
        $directPermissions = $user->permissions;
        if ($directPermissions->isNotEmpty()) {
            $this->info("\n=== Direct Permissions ===");
            $this->line($directPermissions->pluck('name')->join(', '));
        }

        // Sign-in
        $this->info("\n=== Sign-in ===");
        $identity = $user->bspdxIdentity();
        $this->line('bspdx.com: '.($identity ? "Linked ({$identity->subject}, since {$identity->created_at->format('Y-m-d')})" : 'Not linked'));

        // Special status
        $this->info("\n=== Special Status ===");
        if (method_exists($user, 'isSuperAdmin')) {
            $this->line('Super Admin: '.($user->isSuperAdmin() ? 'Yes' : 'No'));
        }

        return 0;
    }
}
