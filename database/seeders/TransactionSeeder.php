<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Attachment;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('role', 'owner')->first();
        $staff = User::where('role', 'staff')->first();

        $cash    = Account::where('code', '1101')->first(); // Kas Utama
        $bank    = Account::where('code', '1102')->first(); // Bank BCA (jika ada)
        $rev1    = Account::where('code', '4101')->first(); // Pendapatan Utama
        $rev2    = Account::where('code', '4102')->first(); // Pendapatan Lain-lain (jika ada)
        $exp1    = Account::where('code', '5101')->first(); // Beban Gaji & Tunjangan
        $exp2    = Account::where('code', '5102')->first(); // Beban Sewa Kantor (jika ada)
        $exp3    = Account::where('code', '5103')->first(); // Beban Utilitas (jika ada)

        // Fallback jika akun tertentu tidak ada
        $bank  = $bank  ?? $cash;
        $rev2  = $rev2  ?? $rev1;
        $exp2  = $exp2  ?? $exp1;
        $exp3  = $exp3  ?? $exp1;

        $data = [
            // Bulan Juni 2026
            ['date' => '2026-06-02', 'type' => 'in',  'amount' => 15000000, 'cashAcc' => $cash, 'catAcc' => $rev1, 'desc' => 'Pembayaran proyek website client PT Maju Bersama', 'user' => $owner],
            ['date' => '2026-06-05', 'type' => 'out', 'amount' => 5500000,  'cashAcc' => $cash, 'catAcc' => $exp1, 'desc' => 'Gaji karyawan Juni 2026', 'user' => $owner],
            ['date' => '2026-06-10', 'type' => 'out', 'amount' => 2000000,  'cashAcc' => $cash, 'catAcc' => $exp2, 'desc' => 'Sewa kantor bulan Juni 2026', 'user' => $staff],
            ['date' => '2026-06-15', 'type' => 'in',  'amount' => 8500000,  'cashAcc' => $cash, 'catAcc' => $rev1, 'desc' => 'Pendapatan jasa konsultasi startup ABC', 'user' => $staff],
            ['date' => '2026-06-18', 'type' => 'out', 'amount' => 450000,   'cashAcc' => $cash, 'catAcc' => $exp3, 'desc' => 'Tagihan listrik & internet Juni 2026', 'user' => $staff],
            ['date' => '2026-06-22', 'type' => 'in',  'amount' => 3200000,  'cashAcc' => $cash, 'catAcc' => $rev2, 'desc' => 'Penjualan template desain UI', 'user' => $owner],
            ['date' => '2026-06-28', 'type' => 'out', 'amount' => 800000,   'cashAcc' => $cash, 'catAcc' => $exp3, 'desc' => 'Pembelian ATK & perlengkapan kantor', 'user' => $staff],

            // Bulan Juli 2026
            ['date' => '2026-07-01', 'type' => 'in',  'amount' => 20000000, 'cashAcc' => $cash, 'catAcc' => $rev1, 'desc' => 'Pembayaran fase 1 proyek e-commerce PT Retail Nusantara', 'user' => $owner],
            ['date' => '2026-07-02', 'type' => 'out', 'amount' => 5500000,  'cashAcc' => $cash, 'catAcc' => $exp1, 'desc' => 'Gaji karyawan Juli 2026', 'user' => $owner],
            ['date' => '2026-07-03', 'type' => 'out', 'amount' => 2000000,  'cashAcc' => $cash, 'catAcc' => $exp2, 'desc' => 'Sewa kantor bulan Juli 2026', 'user' => $staff],
            ['date' => '2026-07-04', 'type' => 'in',  'amount' => 4500000,  'cashAcc' => $cash, 'catAcc' => $rev2, 'desc' => 'Pemasukan dividen investasi deposito', 'user' => $owner],
            ['date' => '2026-07-05', 'type' => 'out', 'amount' => 1200000,  'cashAcc' => $cash, 'catAcc' => $exp3, 'desc' => 'Tagihan hosting server & domain tahunan', 'user' => $staff],
        ];

        foreach ($data as $item) {
            $date   = Carbon::parse($item['date']);
            $userId = $item['user']->id;

            // Generate transaction number
            $prefix    = 'TX-' . $date->format('Ymd') . '-';
            $lastCount = Transaction::where('transaction_number', 'like', $prefix . '%')->count();
            $txNumber  = $prefix . str_pad($lastCount + 1, 4, '0', STR_PAD_LEFT);

            $tx = Transaction::create([
                'transaction_number' => $txNumber,
                'transaction_date'   => $date,
                'description'        => $item['desc'],
                'created_by'         => $userId,
            ]);

            /** @var Account $cashAcc */
            $cashAcc = $item['cashAcc'];
            /** @var Account $catAcc */
            $catAcc = $item['catAcc'];

            if ($item['type'] === 'in') {
                // Cash DEBIT, Revenue CREDIT
                JournalEntry::create(['transaction_id' => $tx->id, 'account_id' => $cashAcc->id, 'type' => 'debit',  'amount' => $item['amount']]);
                JournalEntry::create(['transaction_id' => $tx->id, 'account_id' => $catAcc->id,  'type' => 'credit', 'amount' => $item['amount']]);
            } else {
                // Expense DEBIT, Cash CREDIT
                JournalEntry::create(['transaction_id' => $tx->id, 'account_id' => $catAcc->id,  'type' => 'debit',  'amount' => $item['amount']]);
                JournalEntry::create(['transaction_id' => $tx->id, 'account_id' => $cashAcc->id, 'type' => 'credit', 'amount' => $item['amount']]);
            }
        }

        echo "TransactionSeeder: " . count($data) . " transaksi sample berhasil dibuat.\n";
    }
}
