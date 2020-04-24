<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Cocur\Slugify\Slugify;
use Wink\WinkAuthor;
use Hash;
use Str;

class WinkCreateUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wink:create-user {email} {name} {password?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates a new Wink user';

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
      $slugify = new Slugify();
      WinkAuthor::create([
        'id' => (string) Str::uuid(),
        'name' => $name,
        'slug' => $slugify->slugify($name),
        'bio' => 'I have a bio!',
        'email' => $email,
        'password' => Hash::make($password),
      ]);
      $this->info("DONE");
    }
}
