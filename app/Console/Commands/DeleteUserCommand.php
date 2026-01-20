<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class DeleteUserCommand extends Command
{
    protected $signature = 'user:delete {email? : User email address} {--force : Skip confirmation}';

    protected $description = 'Delete a user account';

    public function handle(): int
    {
        $email = $this->argument('email');
        $force = $this->option('force');

        // Interactive mode
        if (!$email) {
            $email = $this->ask('User email address to delete');
        }

        // Find user
        try {
            $user = User::with('roles')->where('email', $email)->firstOrFail();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->error("User with email '{$email}' not found.");
            return 1;
        }

        // Display user info
        $this->warn("\nYou are about to delete the following user:");
        $this->line("Name: {$user->name}");
        $this->line("Email: {$user->email}");
        $this->line("Roles: " . ($user->getRoleNames()->join(', ') ?: '<none>'));
        $this->newLine();

        // Prevent super-admin deletion
        if ($user->hasRole('super-admin')) {
            $this->error("Cannot delete a super-admin user via CLI for safety reasons.");
            $this->info("Use the web interface or remove super-admin role first.");
            return 1;
        }

        // Confirmation
        if (!$force) {
            $confirmEmail = $this->ask("Type the user's email to confirm deletion");
            if ($confirmEmail !== $email) {
                $this->error('Email does not match. Deletion cancelled.');
                return 1;
            }

            $confirm = $this->confirm('Are you absolutely sure?', false);
            if (!$confirm) {
                $this->info('Deletion cancelled.');
                return 0;
            }
        }

        // Delete user
        $userName = $user->name;
        $user->delete();

        $this->info("User '{$userName}' ({$email}) has been deleted successfully.");

        return 0;
    }
}
