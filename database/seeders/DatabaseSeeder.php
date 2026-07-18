<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ]);

        $user = User::factory()->create([
            'name' => 'User',
            'email' => 'user@example.com',
        ]);

        Task::factory()->count(7)->for($admin)->create();
        Task::factory()->count(5)->for($user)->create();
    }
}