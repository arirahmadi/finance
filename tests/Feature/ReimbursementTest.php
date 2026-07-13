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

class ReimbursementTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $staff;
    protected Account $cashAccount;
    protected Account $liabilityAccount;
    protected Account $expenseAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountSeeder::class);
        $this->cashAccount = Account::where('code', '1101')->first();
        $this->liabilityAccount = Account::where('code', '2101')->first();
        $this->expenseAccount = Account::where('code', '5103')->first();

        // Create standard users
        $this->owner = User::factory()->create(['role' => 'owner']);
        $this->staff = User::factory()->create(['role' => 'staff']);
    }

    /**
     * Test storing a pending reimbursement.
     */
    public function test_can_store_pending_reimbursement(): void
    {
        $response = $this->actingAs($this->owner)->post('/transactions', [
            'type' => 'out',
            'amount' => 500000,
            'account_id' => $this->expenseAccount->id,
            'transaction_date' => '2026-07-13',
            'description' => 'Reimburse keyboard rusak',
            'is_reimbursement' => '1',
            'reimbursement_status' => 'pending',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('success');

        // Check Transaction created correctly
        $tx = Transaction::where('is_reimbursement', true)->first();
        $this->assertNotNull($tx);
        $this->assertEquals('pending', $tx->reimbursement_status);
        $this->assertNull($tx->transfer_proof_path);

        // Check Journal entries: Debit Expense, Credit Liability (2101)
        $this->assertDatabaseHas('journal_entries', [
            'transaction_id' => $tx->id,
            'account_id' => $this->expenseAccount->id,
            'type' => 'debit',
            'amount' => 500000,
        ]);
        $this->assertDatabaseHas('journal_entries', [
            'transaction_id' => $tx->id,
            'account_id' => $this->liabilityAccount->id,
            'type' => 'credit',
            'amount' => 500000,
        ]);
    }

    /**
     * Test transferring a pending reimbursement.
     */
    public function test_can_transfer_pending_reimbursement(): void
    {
        Storage::fake('public');

        // 1. Create a pending reimbursement
        $tx = Transaction::create([
            'transaction_number' => 'TX-20260713-0001',
            'transaction_date' => '2026-07-13',
            'description' => 'Pending Reimbursement',
            'is_reimbursement' => true,
            'reimbursement_status' => 'pending',
            'created_by' => $this->owner->id,
        ]);

        JournalEntry::create([
            'transaction_id' => $tx->id,
            'account_id' => $this->expenseAccount->id,
            'type' => 'debit',
            'amount' => 350000,
        ]);

        JournalEntry::create([
            'transaction_id' => $tx->id,
            'account_id' => $this->liabilityAccount->id,
            'type' => 'credit',
            'amount' => 350000,
        ]);

        // 2. Perform Transfer request
        $proof = UploadedFile::fake()->image('transfer_proof.jpg', 300, 300);
        $response = $this->actingAs($this->owner)->post("/transactions/{$tx->id}/transfer-reimburse", [
            'payment_account_id' => $this->cashAccount->id,
            'transfer_date' => '2026-07-14',
            'transfer_proof' => $proof,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('success');

        // 3. Verify Database and files
        $tx->refresh();
        $this->assertEquals('transferred', $tx->reimbursement_status);
        $this->assertNotNull($tx->transfer_proof_path);
        Storage::disk('public')->assertExists($tx->transfer_proof_path);

        // Journal Entries should have been updated to Debit Expense, Credit Cash
        $this->assertDatabaseHas('journal_entries', [
            'transaction_id' => $tx->id,
            'account_id' => $this->expenseAccount->id,
            'type' => 'debit',
            'amount' => 350000,
        ]);
        $this->assertDatabaseHas('journal_entries', [
            'transaction_id' => $tx->id,
            'account_id' => $this->cashAccount->id,
            'type' => 'credit',
            'amount' => 350000,
        ]);

        // Liability credit entry should be deleted
        $this->assertDatabaseMissing('journal_entries', [
            'transaction_id' => $tx->id,
            'account_id' => $this->liabilityAccount->id,
            'type' => 'credit',
        ]);
    }
}
