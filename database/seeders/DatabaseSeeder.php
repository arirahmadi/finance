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
        User::updateOrCreate(
            ['email' => 'owner@finance.com'],
            [
                'name' => 'Owner Finance',
                'password' => bcrypt('password123'),
                'role' => 'owner',
            ]
        );

        // Create Staff
        User::updateOrCreate(
            ['email' => 'admin@finance.com'],
            [
                'name' => 'Staff Finance',
                'password' => bcrypt('password123'),
                'role' => 'staff',
                'permissions' => [
                    'view_transactions', 'create_transactions', 'edit_transactions', 'delete_transactions', 'approve_transactions',
                    'view_settlements', 'create_settlements', 'process_settlements', 'edit_settlements', 'delete_settlements',
                    'view_cash_advances', 'create_cash_advances', 'edit_cash_advances', 'delete_cash_advances'
                ],
            ]
        );

        $this->call([
            AccountSeeder::class,
        ]);
    }
}
