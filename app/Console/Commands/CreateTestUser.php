<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateTestUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-test-user {email=test@gmail.com} {password=password}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a test user for authentication testing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $password = $this->argument('password');

        $existingUser = User::where('email', $email)->first();
        if ($existingUser) {
            $this->info("User with email {$email} already exists.");
            return;
        }

        $user = new User();
        $user->name = 'Test User';
        $user->email = $email;
        $user->password = bcrypt($password);
        $user->save();

        $this->info("Test user created successfully with email: {$email}");
    }
}
