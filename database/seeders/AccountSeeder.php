<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            // Assets (1000)
            ['code' => '1101', 'name' => 'Kas Utama', 'type' => 'asset'],
            ['code' => '1102', 'name' => 'Bank Mandiri/BCA', 'type' => 'asset'],
            ['code' => '1201', 'name' => 'Piutang Usaha', 'type' => 'asset'],
            ['code' => '1202', 'name' => 'Uang Muka Pembelian', 'type' => 'asset'],
            ['code' => '1203', 'name' => 'Piutang Karyawan (Cash Advance)', 'type' => 'asset'],

            // Liabilities (2000)
            ['code' => '2101', 'name' => 'Utang Usaha', 'type' => 'liability'],

            // Equity (3000)
            ['code' => '3101', 'name' => 'Modal Pendiri', 'type' => 'equity'],
            ['code' => '3201', 'name' => 'Laba Ditahan', 'type' => 'equity'],

            // Revenue (4000)
            ['code' => '4101', 'name' => 'Pendapatan Utama', 'type' => 'revenue'],
            ['code' => '4102', 'name' => 'Pendapatan Lain-lain', 'type' => 'revenue'],

            // Expenses (5000)
            ['code' => '5101', 'name' => 'Beban Gaji & Tunjangan', 'type' => 'expense'],
            ['code' => '5102', 'name' => 'Beban Sewa & Operasional Kantor', 'type' => 'expense'],
            ['code' => '5103', 'name' => 'Beban Server & Langganan Software', 'type' => 'expense'],
            ['code' => '5104', 'name' => 'Beban Pemasaran & Iklan', 'type' => 'expense'],
        ];

        foreach ($accounts as $account) {
            Account::updateOrCreate(['code' => $account['code']], $account);
        }
    }
}
