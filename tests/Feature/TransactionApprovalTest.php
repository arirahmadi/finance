<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Models\Transaction;
use App\Models\JournalEntry;
use App\Models\TransactionApproval;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $staff1;
    protected User $staff2;
    protected User $staff3;
    protected Account $cashAccount;
    protected Account $expenseAccount;
    protected Account $loanAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountSeeder::class);

        $this->cashAccount = Account::where('code', '1101')->first(); // Kas Utama
        $this->expenseAccount = Account::where('code', '5103')->first(); // Beban Server & Software
        $this->loanAccount = Account::where('code', '1203')->first() 
            ?: Account::create(['code' => '1203', 'name' => 'Piutang Karyawan', 'type' => 'asset']);

        // Create owner (has all permissions automatically)
        $this->owner = User::factory()->create(['role' => 'owner']);

        // Create staff users with approve permission
        $this->staff1 = User::factory()->create(['role' => 'staff', 'permissions' => ['approve_transactions']]);
        $this->staff2 = User::factory()->create(['role' => 'staff', 'permissions' => ['approve_transactions']]);
        $this->staff3 = User::factory()->create(['role' => 'staff', 'permissions' => ['approve_transactions']]);
    }

    /**
     * Test transaction is pending and does not affect ledger/summaries until 3 approvals are met.
     */
    public function test_transaction_approval_workflow(): void
    {
        // 1. Create a transaction (starts as pending)
        $payload = [
            'type' => 'out',
            'amount' => 150000.00,
            'account_id' => $this->expenseAccount->id,
            'payment_account_id' => $this->cashAccount->id,
            'transaction_date' => now()->format('Y-m-d'),
            'description' => 'Test Expense Pending Approval',
        ];

        $response = $this->actingAs($this->staff1)->postJson('/api/transactions', $payload);
        $response->assertStatus(201);

        $txId = $response->json('data.id');
        $this->assertDatabaseHas('transactions', [
            'id' => $txId,
            'approval_status' => 'pending'
        ]);

        // 2. Ledger API should not list pending transactions
        $ledgerResponse = $this->actingAs($this->owner)->getJson('/api/ledger?account_id=' . $this->cashAccount->id);
        $ledgerResponse->assertStatus(200);
        $this->assertCount(0, $ledgerResponse->json('entries'));

        // 3. Summaries API should not calculate pending transactions
        $summaryResponse = $this->actingAs($this->owner)->getJson('/api/transactions');
        $summaryResponse->assertStatus(200);
        $this->assertEquals(0, $summaryResponse->json('summary.total_out'));

        // 4. Approver 1 approves
        $approveResponse1 = $this->actingAs($this->staff1)->postJson("/api/transactions/{$txId}/approve");
        $approveResponse1->assertStatus(200)->assertJsonPath('message', 'Persetujuan berhasil disimpan.');

        // Try to approve again by same user (should fail)
        $approveResponseDuplicate = $this->actingAs($this->staff1)->postJson("/api/transactions/{$txId}/approve");
        $approveResponseDuplicate->assertStatus(400);

        // 5. Approver 2 approves
        $approveResponse2 = $this->actingAs($this->staff2)->postJson("/api/transactions/{$txId}/approve");
        $approveResponse2->assertStatus(200)->assertJsonPath('message', 'Persetujuan berhasil disimpan.');

        // 6. Approver 3 approves (should trigger 'approved' status)
        $approveResponse3 = $this->actingAs($this->staff3)->postJson("/api/transactions/{$txId}/approve");
        $approveResponse3->assertStatus(200)->assertJsonPath('message', 'Persetujuan berhasil disimpan.');

        $this->assertDatabaseHas('transactions', [
            'id' => $txId,
            'approval_status' => 'approved'
        ]);

        // 7. Ledger API should now list this transaction
        $ledgerResponseAfter = $this->actingAs($this->owner)->getJson('/api/ledger?account_id=' . $this->cashAccount->id);
        $ledgerResponseAfter->assertStatus(200);
        $this->assertCount(1, $ledgerResponseAfter->json('entries'));

        // 8. Summaries API should now calculate this transaction
        $summaryResponseAfter = $this->actingAs($this->owner)->getJson('/api/transactions');
        $summaryResponseAfter->assertStatus(200);
        $this->assertEquals(150000.00, $summaryResponseAfter->json('summary.total_out'));
    }

    /**
     * Test loan repayments parent update logic upon approval.
     */
    public function test_loan_repayment_approval_updates_parent_loan(): void
    {
        // 1. Create a parent Loan (starts as pending)
        $loan = Transaction::create([
            'transaction_number' => 'CA-20260801-0001',
            'transaction_date' => '2026-08-01',
            'description' => 'Parent Loan Test',
            'recipient_name' => 'John Doe',
            'is_loan' => true,
            'loan_status' => 'open',
            'loan_repaid_amount' => 0.0,
            'amount' => 1000000.0,
            'approval_status' => 'pending',
            'created_by' => $this->owner->id,
        ]);

        JournalEntry::create([
            'transaction_id' => $loan->id,
            'account_id' => $this->loanAccount->id,
            'type' => 'debit',
            'amount' => 1000000.0,
        ]);

        // Approve parent loan so it is active
        TransactionApproval::create(['transaction_id' => $loan->id, 'user_id' => $this->staff1->id]);
        TransactionApproval::create(['transaction_id' => $loan->id, 'user_id' => $this->staff2->id]);
        TransactionApproval::create(['transaction_id' => $loan->id, 'user_id' => $this->staff3->id]);
        $loan->update(['approval_status' => 'approved']);

        // 2. Create a Repayment (starts as pending)
        $repaymentPayload = [
            'payment_account_id' => $this->cashAccount->id,
            'amount' => 400000, // Numeric format
            'transaction_date' => '2026-08-02',
            'description' => 'Installment 1',
        ];

        // We use the WebController equivalent storeRepayment endpoint or direct API storeRepayment
        $response = $this->actingAs($this->staff1)->postJson("/api/cash-advances/{$loan->id}/repay", $repaymentPayload);
        $response->assertStatus(201);

        $repaymentTxId = $response->json('data.id');

        // Recalculate loan repaid amount immediately (should still be 0 because repayment is pending)
        $loan->refresh();
        $this->assertEquals(0.0, $loan->loan_repaid_amount);

        // Approve the repayment (3 steps)
        $this->actingAs($this->staff1)->postJson("/api/transactions/{$repaymentTxId}/approve");
        $this->actingAs($this->staff2)->postJson("/api/transactions/{$repaymentTxId}/approve");
        $this->actingAs($this->staff3)->postJson("/api/transactions/{$repaymentTxId}/approve");

        // Parent loan should now be updated to show 400000 repaid
        $loan->refresh();
        $this->assertEquals(400000.0, $loan->loan_repaid_amount);
        $this->assertEquals('open', $loan->loan_status);
    }
}
