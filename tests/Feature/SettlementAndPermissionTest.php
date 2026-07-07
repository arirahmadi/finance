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

class SettlementAndPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $staff;
    protected Account $cashAccount;
    protected Account $advanceAccount;
    protected Account $expenseAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountSeeder::class);
        $this->cashAccount = Account::where('code', '1101')->first();
        $this->advanceAccount = Account::where('code', '1202')->first();
        $this->expenseAccount = Account::where('code', '5103')->first(); // Beban Server

        $this->owner = User::factory()->create(['role' => 'owner']);
        $this->staff = User::factory()->create([
            'role' => 'staff',
            'permissions' => ['view_transactions', 'create_transactions'] // Staff has no edit/delete permission by default in this test
        ]);
    }

    /**
     * Test dynamic permission checks on transaction update.
     */
    public function test_staff_cannot_edit_transaction_without_permission(): void
    {
        // Create sample transaction
        $tx = Transaction::create([
            'transaction_number' => 'TX-TEST-01',
            'transaction_date' => now(),
            'description' => 'Test Transaction',
            'created_by' => $this->owner->id,
        ]);

        JournalEntry::create(['transaction_id' => $tx->id, 'account_id' => $this->expenseAccount->id, 'type' => 'debit', 'amount' => 100.00]);
        JournalEntry::create(['transaction_id' => $tx->id, 'account_id' => $this->cashAccount->id, 'type' => 'credit', 'amount' => 100.00]);

        // Attempt update as staff (should be forbidden because staff lacks 'edit_transactions' permission)
        $response = $this->actingAs($this->staff)->put('/transactions/' . $tx->id, [
            'type' => 'out',
            'amount' => 120.00,
            'account_id' => $this->expenseAccount->id,
            'payment_account_id' => $this->cashAccount->id,
            'transaction_date' => now()->format('Y-m-d'),
            'description' => 'Try update description',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['auth']);
        
        // Assert description has not changed
        $this->assertDatabaseHas('transactions', [
            'id' => $tx->id,
            'description' => 'Test Transaction'
        ]);
    }

    /**
     * Test staff can edit transaction if granted permission.
     */
    public function test_staff_can_edit_transaction_with_granted_permission(): void
    {
        // Grant edit permission to staff
        $this->staff->update([
            'permissions' => ['view_transactions', 'create_transactions', 'edit_transactions']
        ]);

        $tx = Transaction::create([
            'transaction_number' => 'TX-TEST-02',
            'transaction_date' => now(),
            'description' => 'Test Transaction',
            'created_by' => $this->owner->id,
        ]);

        JournalEntry::create(['transaction_id' => $tx->id, 'account_id' => $this->expenseAccount->id, 'type' => 'debit', 'amount' => 100.00]);
        JournalEntry::create(['transaction_id' => $tx->id, 'account_id' => $this->cashAccount->id, 'type' => 'credit', 'amount' => 100.00]);

        $response = $this->actingAs($this->staff)->put('/transactions/' . $tx->id, [
            'type' => 'out',
            'amount' => 150.00,
            'account_id' => $this->expenseAccount->id,
            'payment_account_id' => $this->cashAccount->id,
            'transaction_date' => now()->format('Y-m-d'),
            'description' => 'Updated by staff',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('success');
        
        $this->assertDatabaseHas('transactions', [
            'id' => $tx->id,
            'description' => 'Updated by staff'
        ]);
    }

    /**
     * Test creating advance payment and checking dynamic bookkeeping entry.
     */
    public function test_user_can_create_advance_payment(): void
    {
        $response = $this->actingAs($this->owner)->post('/settlements/advance', [
            'amount' => 5000000.00,
            'payment_account_id' => $this->cashAccount->id,
            'transaction_date' => now()->format('Y-m-d'),
            'description' => 'Uang muka sewa server Cloud PT Prima',
            'recipient_name' => 'Budi Santoso',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('success');

        // Assert transaction was created and marked as advance
        $this->assertDatabaseHas('transactions', [
            'is_advance' => true,
            'advance_status' => 'open',
            'description' => 'Uang muka sewa server Cloud PT Prima'
        ]);

        $tx = Transaction::where('is_advance', true)->first();

        // Assert double-entry bookkeeping:
        // Debit: 1202 - Uang Muka Pembelian
        $this->assertDatabaseHas('journal_entries', [
            'transaction_id' => $tx->id,
            'account_id' => $this->advanceAccount->id,
            'type' => 'debit',
            'amount' => 5000000.00
        ]);

        // Credit: 1101 - Kas Utama
        $this->assertDatabaseHas('journal_entries', [
            'transaction_id' => $tx->id,
            'account_id' => $this->cashAccount->id,
            'type' => 'credit',
            'amount' => 5000000.00
        ]);
    }

    /**
     * Test settling advance payment with exact matching, underpaid and overpaid scenarios.
     */
    public function test_user_can_settle_advance_payment_overpaid(): void
    {
        Storage::fake('public');
        
        // 1. Create a 3,000,000 advance
        $tx = Transaction::create([
            'transaction_number' => 'TX-ADV-99',
            'transaction_date' => now(),
            'description' => 'Uang muka hosting',
            'is_advance' => true,
            'advance_status' => 'open',
            'created_by' => $this->owner->id
        ]);

        JournalEntry::create(['transaction_id' => $tx->id, 'account_id' => $this->advanceAccount->id, 'type' => 'debit', 'amount' => 3000000.00]);
        JournalEntry::create(['transaction_id' => $tx->id, 'account_id' => $this->cashAccount->id, 'type' => 'credit', 'amount' => 3000000.00]);

        $receipt = UploadedFile::fake()->image('bon.jpg', 300, 300);

        // 2. Settle with 2,500,000 (actual cost is lower, employee returns 500,000 to cash)
        $response = $this->actingAs($this->owner)->post("/settlements/{$tx->id}/settle", [
            'settlement_amount' => 2500000.00,
            'expense_account_id' => $this->expenseAccount->id,
            'receipt' => $receipt,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('success');

        // Check updated status
        $this->assertDatabaseHas('transactions', [
            'id' => $tx->id,
            'advance_status' => 'settled',
            'settlement_amount' => 2500000.00
        ]);

        // Check file uploaded
        $attachment = Attachment::where('transaction_id', $tx->id)->first();
        $this->assertNotNull($attachment);
        Storage::disk('public')->assertExists($attachment->file_path);

        // Check double-entry penyesuaian:
        // Debit: Beban Server (2,500,000)
        $this->assertDatabaseHas('journal_entries', [
            'transaction_id' => $tx->id,
            'account_id' => $this->expenseAccount->id,
            'type' => 'debit',
            'amount' => 2500000.00
        ]);

        // Credit: Uang Muka Pembelian (3,000,000)
        $this->assertDatabaseHas('journal_entries', [
            'transaction_id' => $tx->id,
            'account_id' => $this->advanceAccount->id,
            'type' => 'credit',
            'amount' => 3000000.00
        ]);

        // Debit: Kas Utama (500,000) -- pengembalian dana sisa
        $this->assertDatabaseHas('journal_entries', [
            'transaction_id' => $tx->id,
            'account_id' => $this->cashAccount->id,
            'type' => 'debit',
            'amount' => 500000.00
        ]);
    }

    /**
     * Test settlement permissions.
     */
    public function test_staff_settlement_permissions(): void
    {
        // 1. Test indexSettlements (View Menu)
        // Access denied
        $response = $this->actingAs($this->staff)->get('/settlements');
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['auth']);

        // Access allowed
        $this->staff->update(['permissions' => ['view_settlements']]);
        $response = $this->actingAs($this->staff)->get('/settlements');
        $response->assertStatus(302);
        $response->assertRedirect('/?activeTab=settlements');

        // 2. Test storeAdvance
        // Access denied
        $this->staff->update(['permissions' => ['view_settlements']]);
        $response = $this->actingAs($this->staff)->post('/settlements/advance', [
            'amount' => 1000.00,
            'payment_account_id' => $this->cashAccount->id,
            'transaction_date' => now()->format('Y-m-d'),
            'description' => 'Test advance',
            'recipient_name' => 'Budi',
        ]);
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['auth']);

        // Access allowed
        $this->staff->update(['permissions' => ['view_settlements', 'create_settlements']]);
        $response = $this->actingAs($this->staff)->post('/settlements/advance', [
            'amount' => 1000.00,
            'payment_account_id' => $this->cashAccount->id,
            'transaction_date' => now()->format('Y-m-d'),
            'description' => 'Test advance allowed',
            'recipient_name' => 'Budi',
        ]);
        $response->assertStatus(302);
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('transactions', ['recipient_name' => 'Budi', 'description' => 'Test advance allowed']);

        // Get the created advance transaction
        $tx = Transaction::where('description', 'Test advance allowed')->first();

        // 3. Test settleAdvance
        Storage::fake('public');
        $file = UploadedFile::fake()->image('receipt.jpg', 300, 300);

        // Access denied
        $this->staff->update(['permissions' => ['view_settlements']]);
        $response = $this->actingAs($this->staff)->post('/settlements/' . $tx->id . '/settle', [
            'settlement_amount' => 1000.00,
            'expense_account_id' => $this->expenseAccount->id,
            'receipt' => $file,
        ]);
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['auth']);

        // Access allowed
        $this->staff->update(['permissions' => ['view_settlements', 'process_settlements']]);
        $response = $this->actingAs($this->staff)->post('/settlements/' . $tx->id . '/settle', [
            'settlement_amount' => 1000.00,
            'expense_account_id' => $this->expenseAccount->id,
            'receipt' => $file,
        ]);
        $response->assertStatus(302);
        $response->assertSessionHas('success');

        // 4. Test deleteSettlement
        // Access denied
        $this->staff->update(['permissions' => ['view_settlements']]);
        $response = $this->actingAs($this->staff)->delete('/settlements/' . $tx->id);
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['auth']);
        $this->assertDatabaseHas('transactions', ['id' => $tx->id]);

        // Access allowed
        $this->staff->update(['permissions' => ['view_settlements', 'delete_settlements']]);
        $response = $this->actingAs($this->staff)->delete('/settlements/' . $tx->id);
        $response->assertStatus(302);
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('transactions', ['id' => $tx->id]);
    }

    /**
     * Test bulk delete settlements.
     */
    public function test_bulk_delete_settlements(): void
    {
        // Create 2 advances
        $tx1 = Transaction::create([
            'transaction_number' => 'TX-ADV-1',
            'transaction_date' => now(),
            'description' => 'Advance 1',
            'recipient_name' => 'Budi',
            'is_advance' => true,
            'advance_status' => 'open',
            'created_by' => $this->owner->id,
        ]);
        $tx2 = Transaction::create([
            'transaction_number' => 'TX-ADV-2',
            'transaction_date' => now(),
            'description' => 'Advance 2',
            'recipient_name' => 'Toni',
            'is_advance' => true,
            'advance_status' => 'open',
            'created_by' => $this->owner->id,
        ]);

        // Try bulk delete as staff without permission
        $response = $this->actingAs($this->staff)->delete('/settlements/bulk', [
            'ids' => [$tx1->id, $tx2->id]
        ]);
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['auth']);
        $this->assertDatabaseHas('transactions', ['id' => $tx1->id]);

        // Try bulk delete as staff with permission
        $this->staff->update(['permissions' => ['delete_settlements']]);
        $response = $this->actingAs($this->staff)->delete('/settlements/bulk', [
            'ids' => [$tx1->id, $tx2->id]
        ]);
        $response->assertStatus(302);
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('transactions', ['id' => $tx1->id]);
        $this->assertDatabaseMissing('transactions', ['id' => $tx2->id]);
    }

    /**
     * Test edit settlement / advance.
     */
    public function test_edit_settlement(): void
    {
        // Create an open advance
        $tx = Transaction::create([
            'transaction_number' => 'TX-ADV-EDIT-TEST',
            'transaction_date' => now(),
            'description' => 'Original description',
            'recipient_name' => 'Original recipient',
            'is_advance' => true,
            'advance_status' => 'open',
            'created_by' => $this->owner->id,
        ]);

        JournalEntry::create(['transaction_id' => $tx->id, 'account_id' => $this->advanceAccount->id, 'type' => 'debit', 'amount' => 1000.00]);
        JournalEntry::create(['transaction_id' => $tx->id, 'account_id' => $this->cashAccount->id, 'type' => 'credit', 'amount' => 1000.00]);

        // 1. Try editing without edit_settlements permission
        $response = $this->actingAs($this->staff)->put('/settlements/' . $tx->id, [
            'transaction_date' => now()->format('Y-m-d'),
            'recipient_name' => 'Staff Edit Attempt',
            'amount' => 1200.00,
            'payment_account_id' => $this->cashAccount->id,
            'description' => 'Staff edit attempt desc',
        ]);
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['auth']);
        $this->assertDatabaseHas('transactions', ['id' => $tx->id, 'recipient_name' => 'Original recipient']);

        // 2. Edit with edit_settlements permission (Open Advance)
        $this->staff->update(['permissions' => ['edit_settlements']]);
        $response = $this->actingAs($this->staff)->put('/settlements/' . $tx->id, [
            'transaction_date' => now()->format('Y-m-d'),
            'recipient_name' => 'Staff Edit Allowed',
            'amount' => 1200.00,
            'payment_account_id' => $this->cashAccount->id,
            'description' => 'Staff edit allowed desc',
        ]);
        $response->assertStatus(302);
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('transactions', ['id' => $tx->id, 'recipient_name' => 'Staff Edit Allowed']);

        // Verify Jurnal entries updated correctly
        $this->assertDatabaseHas('journal_entries', ['transaction_id' => $tx->id, 'account_id' => $this->advanceAccount->id, 'type' => 'debit', 'amount' => 1200.00]);
        $this->assertDatabaseHas('journal_entries', ['transaction_id' => $tx->id, 'account_id' => $this->cashAccount->id, 'type' => 'credit', 'amount' => 1200.00]);

        // 3. Settle it so we can test editing settled state
        $this->staff->update(['permissions' => ['process_settlements']]);
        Storage::fake('public');
        $file = UploadedFile::fake()->image('receipt.jpg', 300, 300);
        $this->actingAs($this->staff)->post('/settlements/' . $tx->id . '/settle', [
            'settlement_amount' => 1500.00,
            'expense_account_id' => $this->expenseAccount->id,
            'receipt' => $file,
        ]);

        // 4. Edit settled state (edit_settlements permission)
        $this->staff->update(['permissions' => ['edit_settlements']]);
        $newFile = UploadedFile::fake()->image('new_receipt.jpg', 400, 400);
        $response = $this->actingAs($this->staff)->put('/settlements/' . $tx->id, [
            'transaction_date' => now()->format('Y-m-d'),
            'recipient_name' => 'Staff Edit Settled',
            'amount' => 1200.00, // advance amount unchanged
            'payment_account_id' => $this->cashAccount->id,
            'description' => 'Staff edit settled desc',
            'settlement_amount' => 1400.00, // settlement amount changed (original 1500)
            'expense_account_id' => $this->expenseAccount->id,
            'receipt' => $newFile,
        ]);
        $response->assertStatus(302);
        $response->assertSessionHas('success');

        // Check updated fields
        $this->assertDatabaseHas('transactions', [
            'id' => $tx->id,
            'recipient_name' => 'Staff Edit Settled',
            'settlement_amount' => 1400.00,
            'advance_status' => 'settled',
        ]);

        // Check Jurnal entries adjusted correctly:
        // Debit: Beban Server (1400.00)
        $this->assertDatabaseHas('journal_entries', [
            'transaction_id' => $tx->id,
            'account_id' => $this->expenseAccount->id,
            'type' => 'debit',
            'amount' => 1400.00
        ]);
        // Credit: Kas Utama (200.00) -- difference 1400 - 1200 = 200
        $this->assertDatabaseHas('journal_entries', [
            'transaction_id' => $tx->id,
            'account_id' => $this->cashAccount->id,
            'type' => 'credit',
            'amount' => 200.00
        ]);
    }
}
