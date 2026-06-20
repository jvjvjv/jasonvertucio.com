<?php

namespace App\Console\Commands;

use App\Models\User;
use Canvas\Models\User as CanvasUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ChangeUserPasswordCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:password {email} {new-password?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Changes a user's password";

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
        $password = $this->argument('new-password');
        if ($password == null) {
            $password = $this->secret('Enter new password');
            $password_confirm = $this->secret('Enter password again to confirm');
            if ($password != $password_confirm) {
                $this->error("Passwords don't match!");

                return 1;
            }
        }
        $user = User::whereEmail($email)->firstOrFail();
        $user->password = Hash::make($password);
        $user->save();
        $this->info('DONE');

        return 0;
    }
}
