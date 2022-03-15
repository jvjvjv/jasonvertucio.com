<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Canvas\Models\User as CanvasUser;
use App\Models\User;
use Hash;

class CreateUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create {email} {name} {password?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates a new user';

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
     *
     * @return mixed
     */
    public function handle()
    {
      $email = $this->argument('email');
      $name = $this->argument('name');
      $password = $this->argument('password');
      if ($password == null) {
        $password = $this->secret('Enter new password');
        $password_confirm = $this->secret('Enter password again to confirm');
        if ($password != $password_confirm) {
          $this->error("Passwords don't match!");
          return 1;
        }
      }
      User::create([
        'name' => $name,
        'email' => $email,
        'password' => Hash::make($password),
      ]);
      $this->info("DONE");
    }
}
