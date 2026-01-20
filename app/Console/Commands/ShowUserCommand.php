<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class ShowUserCommand extends Command
{
    protected $signature = 'user:info {email : User email address}';

    protected $description = 'Display detailed information about a user';

    public function handle(): int
    {
        $email = $this->argument('email');

        try {
            $user = User::with(['roles.permissions', 'passkeys'])
                ->where('email', $email)
                ->firstOrFail();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->error("User with email '{$email}' not found.");
            return 1;
        }

        // Basic info
        $this->info("\n=== User Information ===");
        $this->line("ID: {$user->id}");
        $this->line("Name: {$user->name}");
        $this->line("Email: {$user->email}");
        $this->line("Email Verified: " . ($user->email_verified_at ? "Yes ({$user->email_verified_at->format('Y-m-d H:i:s')})" : "No"));
        $this->line("Created: {$user->created_at->format('Y-m-d H:i:s')}");
        $this->line("Updated: {$user->updated_at->format('Y-m-d H:i:s')}");

        // Roles
        $this->info("\n=== Roles ===");
        if ($user->roles->isEmpty()) {
            $this->warn("No roles assigned");
        } else {
            $this->table(['Role', 'Guard'], $user->roles->map(fn($role) => [
                $role->name,
                $role->guard_name
            ]));
        }

        // Permissions (via roles)
        $this->info("\n=== Permissions (via roles) ===");
        $permissions = $user->getAllPermissions();
        if ($permissions->isEmpty()) {
            $this->warn("No permissions");
        } else {
            $this->line($permissions->pluck('name')->sort()->join(', '));
        }

        // Direct permissions
        $directPermissions = $user->permissions;
        if ($directPermissions->isNotEmpty()) {
            $this->info("\n=== Direct Permissions ===");
            $this->line($directPermissions->pluck('name')->join(', '));
        }

        // Authentication methods
        $this->info("\n=== Authentication ===");
        $this->line("Password: Yes");
        $this->line("2FA Enabled: " . ($user->hasTwoFactorEnabled() ? "Yes" : "No"));
        if (method_exists($user, 'requires2FA')) {
            $this->line("2FA Required: " . ($user->requires2FA() ? "Yes (by role)" : "No"));
        }
        $this->line("Passkeys Registered: " . ($user->hasPasskeysRegistered() ? "Yes ({$user->passkeys->count()})" : "No"));
        if (method_exists($user, 'requiresPasskey')) {
            $this->line("Passkey Required: " . ($user->requiresPasskey() ? "Yes (by role)" : "No"));
        }

        // Special status
        $this->info("\n=== Special Status ===");
        if (method_exists($user, 'isSuperAdmin')) {
            $this->line("Super Admin: " . ($user->isSuperAdmin() ? "Yes" : "No"));
        }
        if (method_exists($user, 'canManageBinshopsBlogPosts')) {
            $this->line("Can Manage Blog: " . ($user->canManageBinshopsBlogPosts() ? "Yes" : "No"));
        }

        return 0;
    }
}
