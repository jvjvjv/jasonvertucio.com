<?php

namespace App\Console\Commands;

use App\Models\User;
use BSPDX\Keystone\Models\KeystoneRole as Role;
use Illuminate\Console\Command;

class ListUsersCommand extends Command
{
    protected $signature = 'user:list {--role= : Filter by role} {--format=table : Output format (table|json)}';

    protected $description = 'List all users with their roles and authentication status';

    public function handle(): int
    {
        $roleFilter = $this->option('role');
        $format = $this->option('format');

        // Query users
        $query = User::with('roles')->orderBy('email');

        if ($roleFilter) {
            // Validate role exists
            $roleExists = Role::where('name', $roleFilter)->exists();
            if (! $roleExists) {
                $this->error("Role '{$roleFilter}' does not exist.");
                $availableRoles = Role::pluck('name')->toArray();
                $this->info('Available roles: '.implode(', ', $availableRoles));

                return 1;
            }
            $query->role($roleFilter);
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->warn('No users found.');

            return 0;
        }

        if ($format === 'json') {
            $this->line(json_encode($users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames(),
                    'email_verified' => ! is_null($user->email_verified_at),
                    '2fa_enabled' => $user->hasTwoFactorEnabled(),
                    'passkeys' => $user->hasPasskeysRegistered(),
                ];
            }), JSON_PRETTY_PRINT));

            return 0;
        }

        // Table format
        $this->table(
            ['ID', 'Name', 'Email', 'Roles', 'Verified', '2FA', 'Passkeys'],
            $users->map(function ($user) {
                return [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->getRoleNames()->join(', ') ?: '<none>',
                    $user->email_verified_at ? '✓' : '✗',
                    $user->hasTwoFactorEnabled() ? '✓' : '✗',
                    $user->hasPasskeysRegistered() ? '✓' : '✗',
                ];
            })
        );

        $this->info("\nTotal users: ".$users->count());

        return 0;
    }
}
