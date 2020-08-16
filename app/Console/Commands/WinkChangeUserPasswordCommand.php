<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Wink\WinkAuthor;
use Hash;
use Str;

class WinkChangeUserPasswordCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wink:change-user-password {email} {new-password}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
      $password = $this->argument('new-password');
      if ($password == null) {
        $password = $this->secret('Enter new password');
        $password_confirm = $this->secret('Enter password again to confirm');
        if ($password != $password_confirm) {
          $this->error("Passwords don't match!");
          return 1;
        }
      }
      $user = WinkAuthor::whereEmail($email)->first();
      $user->password = Hash::make($password);
      $user->save();
      $this->info("DONE");
    }
}
