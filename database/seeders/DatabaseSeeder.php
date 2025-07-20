<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Task;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create test users with different language preferences
        $testUser = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'preferred_language' => 'en',
            'timezone' => 'UTC',
        ]);

        $germanUser = User::factory()->create([
            'name' => 'German User',
            'email' => 'german@example.com',
            'password' => Hash::make('password'),
            'preferred_language' => 'de',
            'timezone' => 'Europe/Berlin',
        ]);

        $frenchUser = User::factory()->create([
            'name' => 'French User',
            'email' => 'french@example.com',
            'password' => Hash::make('password'),
            'preferred_language' => 'fr',
            'timezone' => 'Europe/Paris',
        ]);

        // Create additional test users
        $additionalUsers = User::factory(5)->create();

        // Seed tasks using the dedicated TaskSeeder
        $this->call(TaskSeeder::class);

        $this->command->info('Database seeded successfully!');
        $this->command->info('Test users created:');
        $this->command->info('- test@example.com (password: password) - English');
        $this->command->info('- german@example.com (password: password) - German');
        $this->command->info('- french@example.com (password: password) - French');
    }
}
