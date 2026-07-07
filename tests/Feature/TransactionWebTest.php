<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Attachment;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TransactionWebTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $staff;
    protected Account $cashAccount;
    protected Account $expenseAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountSeeder::class);
        $this->cashAccount = Account::where('code', '1101')->first();
        $this->expenseAccount = Account::where('code', '5103')->first();

        $this->owner = User::factory()->create(['role' => 'owner']);
        $this->staff = User::factory()->create(['role' => 'staff']);
    }

    /**
     * Helper to create a transaction with a physical receipt file.
     */
    private function createTransactionWithReceipt(User $user, string $desc): Transaction
    {
        $receipt = UploadedFile::fake()->image('receipt.jpg', 400, 400);
        $path = $receipt->store('receipts', 'public');

        $tx = Transaction::create([
            'transaction_number' => 'TX-' . uniqid(),
            'transaction_date' => now(),
            'description' => $desc,
            'created_by' => $user->id,
        ]);

        JournalEntry::create([
            'transaction_id' => $tx->id,
            'account_id' => $this->expenseAccount->id,
            'type' => 'debit',
            'amount' => 1000.00
        ]);

        JournalEntry::create([
            'transaction_id' => $tx->id,
            'account_id' => $this->cashAccount->id,
            'type' => 'credit',
            'amount' => 1000.00
        ]);

        Attachment::create([
            'transaction_id' => $tx->id,
            'file_path' => $path,
            'original_name' => 'receipt.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024
        ]);

        return $tx;
    }

    /**
     * Test that owner can bulk delete transactions.
     */
    public function test_owner_can_bulk_delete_transactions(): void
    {
        Storage::fake('public');

        // Create 3 transactions
        $tx1 = $this->createTransactionWithReceipt($this->owner, 'Tx One');
        $tx2 = $this->createTransactionWithReceipt($this->owner, 'Tx Two');
        $tx3 = $this->createTransactionWithReceipt($this->owner, 'Tx Three');

        $file1 = $tx1->attachments->first()->file_path;
        $file2 = $tx2->attachments->first()->file_path;
        $file3 = $tx3->attachments->first()->file_path;

        Storage::disk('public')->assertExists($file1);
        Storage::disk('public')->assertExists($file2);
        Storage::disk('public')->assertExists($file3);

        // Bulk delete tx1 and tx2, leaving tx3 intact
        $response = $this->actingAs($this->owner)->delete('/transactions/bulk', [
            'ids' => [$tx1->id, $tx2->id]
        ]);

        $response->assertStatus(302); // Redirect back
        $response->assertSessionHas('success', 'Transaksi terpilih berhasil dihapus!');

        // Check DB missing tx1 & tx2 but has tx3
        $this->assertDatabaseMissing('transactions', ['id' => $tx1->id]);
        $this->assertDatabaseMissing('transactions', ['id' => $tx2->id]);
        $this->assertDatabaseHas('transactions', ['id' => $tx3->id]);

        // Check Cascaded Journal Entries
        $this->assertDatabaseMissing('journal_entries', ['transaction_id' => $tx1->id]);
        $this->assertDatabaseMissing('journal_entries', ['transaction_id' => $tx2->id]);

        // Check Cascaded Attachments
        $this->assertDatabaseMissing('attachments', ['transaction_id' => $tx1->id]);
        $this->assertDatabaseMissing('attachments', ['transaction_id' => $tx2->id]);

        // Check physical files deletion
        Storage::disk('public')->assertMissing($file1);
        Storage::disk('public')->assertMissing($file2);
        Storage::disk('public')->assertExists($file3);
    }

    /**
     * Test that staff cannot bulk delete transactions.
     */
    public function test_staff_cannot_bulk_delete_transactions(): void
    {
        $tx = $this->createTransactionWithReceipt($this->owner, 'Tx Staff Block');

        $response = $this->actingAs($this->staff)->delete('/transactions/bulk', [
            'ids' => [$tx->id]
        ]);

        $response->assertStatus(302); // Redirect back with errors
        $response->assertSessionHasErrors(['auth']);

        // Check DB still has transaction
        $this->assertDatabaseHas('transactions', ['id' => $tx->id]);
    }

    /**
     * Test that the fallback storage route serves files and blocks traversal.
     */
    public function test_fallback_storage_route_serves_files_and_blocks_traversal(): void
    {
        $testFile = storage_path('app/public/receipts/test_unit.pdf');
        $dir = dirname($testFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($testFile, 'PDF CONTENT');

        try {
            // Test normal access
            $response = $this->get('/attachments/receipts/test_unit.pdf');
            $response->assertStatus(200);
            $this->assertEquals($testFile, $response->getFile()->getPathname());

            // Test non-existent file
            $response = $this->get('/attachments/receipts/doesnotexist.pdf');
            $response->assertStatus(404);

            // Test directory traversal attempt
            $response = $this->get('/attachments/../../etc/passwd');
            $response->assertStatus(403);
        } finally {
            if (file_exists($testFile)) {
                unlink($testFile);
            }
        }
    }
}
