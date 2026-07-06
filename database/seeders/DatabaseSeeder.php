<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Owner
        User::create([
            'name' => 'Owner Finance',
            'email' => 'owner@finance.com',
            'password' => bcrypt('password123'),
            'role' => 'owner',
        ]);

        // Create Staff
        User::create([
            'name' => 'Staff Finance',
            'email' => 'admin@finance.com',
            'password' => bcrypt('password123'),
            'role' => 'staff',
            'permissions' => ['view_transactions', 'create_transactions', 'edit_transactions', 'delete_transactions'],
        ]);

        $this->call([
            AccountSeeder::class,
        ]);
    }
}
