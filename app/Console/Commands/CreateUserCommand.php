<?php

namespace App\Console\Commands;

use App\Models\User;
use BSPDX\Keystone\Models\KeystoneRole as Role;
use Canvas\Models\User as CanvasUser;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;

class CreateUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create {email} {name} {password?} {--role= : Assign a role}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates a new user with optional role assignment';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        $name = $this->argument('name');
        $password = $this->argument('password');
        $roleName = $this->option('role');

        // Validate user doesn't exist
        if (User::where('email', $email)->exists()) {
            $this->error("User with email '{$email}' already exists.");

            return 1;
        }

        if ($password == null) {
            $password = $this->secret('Enter new password');
            $password_confirm = $this->secret('Enter password again to confirm');
            if ($password != $password_confirm) {
                $this->error("Passwords don't match!");

                return 1;
            }
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $this->info('User created successfully!');

        // Role assignment
        if ($roleName) {
            try {
                $role = Role::where('name', $roleName)->firstOrFail();
                $user->assignRole($role);
                $this->info("Role '{$roleName}' assigned.");
            } catch (ModelNotFoundException $e) {
                $this->warn("Role '{$roleName}' does not exist. User created without role.");
                $availableRoles = Role::pluck('name')->toArray();
                $this->info('Available roles: '.implode(', ', $availableRoles));
            }
        }

        $this->line("Email: {$email}");
        $this->line('Roles: '.($user->getRoleNames()->join(', ') ?: '<none>'));

        return 0;
    }
}
