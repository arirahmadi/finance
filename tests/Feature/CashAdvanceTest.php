<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashAdvanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $staff;
    protected Account $cashAccount;
    protected Account $loanAccount;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed COA
        $this->seed(AccountSeeder::class);
        
        $this->cashAccount = Account::where('code', '1101')->first();
        $this->loanAccount = Account::where('code', '1203')->first();

        // Create Users
        $this->owner = User::factory()->create(['role' => 'owner']);
        $this->staff = User::factory()->create([
            'role' => 'staff',
            'permissions' => []
        ]);
    }

    /**
     * Test creating a loan with proper permissions.
     */
    public function test_user_with_permission_can_create_loan(): void
    {
        $staffWithPermission = User::factory()->create([
            'role' => 'staff',
            'permissions' => ['view_cash_advances', 'create_cash_advances']
        ]);

        $response = $this->actingAs($staffWithPermission)->post('/cash-advances', [
            'transaction_date' => '2026-07-10',
            'recipient_name' => 'Budi Santoso',
            'amount' => '1.500.000',
            'payment_account_id' => $this->cashAccount->id,
            'description' => 'Pinjaman Uang Sekolah Anak',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Assert transaction was created
        $tx = Transaction::where('is_loan', true)->first();
        $this->assertNotNull($tx);
        $this->assertEquals('Budi Santoso', $tx->recipient_name);
        $this->assertEquals(0, floatval($tx->loan_repaid_amount));
        $this->assertEquals('open', $tx->loan_status);
        $this->assertStringStartsWith('CA-20260710-', $tx->transaction_number);

        // Assert double-entry journals: Debit 1203, Credit 1101
        $this->assertDatabaseHas('journal_entries', [
            'transaction_id' => $tx->id,
            'account_id' => $this->loanAccount->id,
            'type' => 'debit',
            'amount' => 1500000.00
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'transaction_id' => $tx->id,
            'account_id' => $this->cashAccount->id,
            'type' => 'credit',
            'amount' => 1500000.00
        ]);
    }

    /**
     * Test user without permission cannot create loan.
     */
    public function test_user_without_permission_cannot_create_loan(): void
    {
        $response = $this->actingAs($this->staff)->post('/cash-advances', [
            'transaction_date' => '2026-07-10',
            'recipient_name' => 'Budi Santoso',
            'amount' => '1.500.000',
            'payment_account_id' => $this->cashAccount->id,
            'description' => 'Pinjaman',
        ]);

        $response->assertSessionHasErrors('auth');
        $this->assertEquals(0, Transaction::where('is_loan', true)->count());
    }

    /**
     * Test editing a loan.
     */
    public function test_authorized_user_can_edit_loan(): void
    {
        $staffWithPermission = User::factory()->create([
            'role' => 'staff',
            'permissions' => ['edit_cash_advances']
        ]);

        // Create initial loan
        $loan = Transaction::create([
            'transaction_number' => 'CA-20260710-0001',
            'transaction_date' => '2026-07-10',
            'recipient_name' => 'Budi Santoso',
            'description' => 'Pinjaman Awal',
            'is_loan' => true,
            'loan_status' => 'open',
            'loan_repaid_amount' => 0,
            'created_by' => $this->owner->id
        ]);

        JournalEntry::create([
            'transaction_id' => $loan->id,
            'account_id' => $this->loanAccount->id,
            'type' => 'debit',
            'amount' => 1000000.00
        ]);

        JournalEntry::create([
            'transaction_id' => $loan->id,
            'account_id' => $this->cashAccount->id,
            'type' => 'credit',
            'amount' => 1000000.00
        ]);

        // Edit amount and name
        $response = $this->actingAs($staffWithPermission)->put("/cash-advances/{$loan->id}", [
            'transaction_date' => '2026-07-11',
            'recipient_name' => 'Budi Santoso Edit',
            'amount' => '2.500.000',
            'payment_account_id' => $this->cashAccount->id,
            'description' => 'Pinjaman Awal Edit',
        ]);

        $response->assertRedirect();
        
        $loan->refresh();
        $this->assertEquals('Budi Santoso Edit', $loan->recipient_name);
        
        // Assert old journal entries were cleaned and new ones created
        $this->assertEquals(2, $loan->journalEntries()->count());
        $this->assertEquals(2500000.00, floatval($loan->journalEntries()->first()->amount));
    }

    /**
     * Test loan repayment recording and status updates.
     */
    public function test_loan_repayment_process(): void
    {
        $staffWithPermission = User::factory()->create([
            'role' => 'staff',
            'permissions' => ['create_cash_advances']
        ]);

        // 1. Create a loan of Rp 1.000.000
        $loan = Transaction::create([
            'transaction_number' => 'CA-20260710-0001',
            'transaction_date' => '2026-07-10',
            'recipient_name' => 'Budi Santoso',
            'description' => 'Pinjaman',
            'is_loan' => true,
            'loan_status' => 'open',
            'loan_repaid_amount' => 0,
            'created_by' => $this->owner->id
        ]);

        JournalEntry::create([
            'transaction_id' => $loan->id,
            'account_id' => $this->loanAccount->id,
            'type' => 'debit',
            'amount' => 1000000.00
        ]);

        // 2. Pay first installment: Rp 400.000
        $response1 = $this->actingAs($staffWithPermission)->post("/cash-advances/{$loan->id}/repay", [
            'transaction_date' => '2026-07-11',
            'amount' => '400.000',
            'payment_account_id' => $this->cashAccount->id,
            'description' => 'Angsuran 1',
        ]);

        $response1->assertRedirect();
        $loan->refresh();
        $this->assertEquals(400000.00, floatval($loan->loan_repaid_amount));
        $this->assertEquals('open', $loan->loan_status);

        // Verify journals of repayment (Debit Cash, Credit 1203)
        $repayment = Transaction::where('loan_parent_id', $loan->id)->first();
        $this->assertNotNull($repayment);
        $this->assertDatabaseHas('journal_entries', [
            'transaction_id' => $repayment->id,
            'account_id' => $this->cashAccount->id,
            'type' => 'debit',
            'amount' => 400000.00
        ]);
        $this->assertDatabaseHas('journal_entries', [
            'transaction_id' => $repayment->id,
            'account_id' => $this->loanAccount->id,
            'type' => 'credit',
            'amount' => 400000.00
        ]);

        // 3. Pay second installment that fully pays off the remaining Rp 600.000
        $response2 = $this->actingAs($staffWithPermission)->post("/cash-advances/{$loan->id}/repay", [
            'transaction_date' => '2026-07-12',
            'amount' => '600.000',
            'payment_account_id' => $this->cashAccount->id,
            'description' => 'Angsuran Lunas',
        ]);

        $response2->assertRedirect();
        $loan->refresh();
        $this->assertEquals(1000000.00, floatval($loan->loan_repaid_amount));
        $this->assertEquals('repaid', $loan->loan_status);
    }

    /**
     * Test deleting repayment restores loan remaining balance.
     */
    public function test_delete_repayment_restores_loan_balance(): void
    {
        $staffWithPermission = User::factory()->create([
            'role' => 'staff',
            'permissions' => ['delete_cash_advances']
        ]);

        $loan = Transaction::create([
            'transaction_number' => 'CA-20260710-0001',
            'transaction_date' => '2026-07-10',
            'recipient_name' => 'Budi Santoso',
            'is_loan' => true,
            'loan_status' => 'open',
            'loan_repaid_amount' => 400000.00,
            'created_by' => $this->owner->id
        ]);

        JournalEntry::create([
            'transaction_id' => $loan->id,
            'account_id' => $this->loanAccount->id,
            'type' => 'debit',
            'amount' => 1000000.00
        ]);

        $rep = Transaction::create([
            'transaction_number' => 'CAR-20260711-0001',
            'transaction_date' => '2026-07-11',
            'is_loan' => false,
            'loan_parent_id' => $loan->id,
            'created_by' => $this->owner->id
        ]);

        JournalEntry::create([
            'transaction_id' => $rep->id,
            'account_id' => $this->cashAccount->id,
            'type' => 'debit',
            'amount' => 400000.00
        ]);

        // Delete the repayment
        $response = $this->actingAs($staffWithPermission)->delete("/cash-advances/repay/{$rep->id}");

        $response->assertRedirect();
        
        $loan->refresh();
        $this->assertEquals(0, floatval($loan->loan_repaid_amount));
        $this->assertEquals('open', $loan->loan_status);
        $this->assertDatabaseMissing('transactions', ['id' => $rep->id]);
    }
}
