<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Transaction;

$loans = Transaction::with(['journalEntries.account'])
    ->where('is_loan', true)
    ->get();

foreach ($loans as $tx) {
    echo "TX: " . $tx->transaction_number . "\n";
    echo "  Header Amount: " . var_export($tx->amount, true) . "\n";
    echo "  Casts Amount: " . var_export($tx->getAttribute('amount'), true) . "\n";
    
    $amount = floatval($tx->amount ?? 0);
    echo "  Floatval Amount: " . $amount . "\n";
    
    if ($amount === 0) {
        echo "  Entering fallback loop...\n";
        foreach ($tx->journalEntries as $entry) {
            echo "    Entry Code: " . $entry->account->code . ", Type: " . $entry->type . ", Amount: " . $entry->amount . "\n";
            if (str_starts_with($entry->account->code, '1203') && $entry->type === 'debit') {
                $amount = floatval($entry->amount);
            }
        }
    }
    echo "  Final Calculated Amount: " . $amount . "\n\n";
}
