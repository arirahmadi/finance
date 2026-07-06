<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\JournalEntry;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TransactionController extends Controller
{
    /**
     * Display a listing of the transactions with filter and summaries.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : null;
        $endDate = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : null;

        $query = Transaction::with(['journalEntries.account', 'attachments', 'creator'])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc');

        if ($startDate) {
            $query->where('transaction_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('transaction_date', '<=', $endDate);
        }

        $transactions = $query->get();

        // Calculate summaries (total uang masuk and total uang keluar)
        // Uang Masuk (Cash In): Debit to Asset accounts (e.g. Kas/Bank code starting with 11) with offset Credit to Revenue (starting with 4)
        // Uang Keluar (Cash Out): Credit to Asset accounts (starting with 11) with offset Debit to Expense (starting with 5)
        $totalIn = 0;
        $totalOut = 0;

        $formattedTransactions = $transactions->map(function ($tx) use (&$totalIn, &$totalOut) {
            // Find cash/bank account movement to determine if it is In or Out
            // Cash Inflow: Debit to Asset account
            // Cash Outflow: Credit to Asset account
            $type = 'unknown';
            $amount = 0;
            $category = null;
            $paymentSource = null;

            foreach ($tx->journalEntries as $entry) {
                $accCode = $entry->account->code;
                $isAsset = Str::startsWith($accCode, '11'); // Kas & Bank

                if ($isAsset) {
                    $paymentSource = $entry->account->name;
                    $amount = floatval($entry->amount);
                    if ($entry->type === 'debit') {
                        $type = 'in'; // Cash In (Kas bertambah)
                    } else {
                        $type = 'out'; // Cash Out (Kas berkurang)
                    }
                } else {
                    $category = $entry->account->name;
                }
            }

            // Fallback amount if no asset account is mapped
            if ($amount === 0 && $tx->journalEntries->isNotEmpty()) {
                $amount = floatval($tx->journalEntries->first()->amount);
            }

            if ($type === 'in') {
                $totalIn += $amount;
            } elseif ($type === 'out') {
                $totalOut += $amount;
            }

            return [
                'id' => $tx->id,
                'transaction_number' => $tx->transaction_number,
                'transaction_date' => $tx->transaction_date->format('Y-m-d'),
                'description' => $tx->description,
                'type' => $type,
                'amount' => $amount,
                'category' => $category,
                'payment_source' => $paymentSource,
                'attachments' => $tx->attachments,
                'created_by' => $tx->creator->name ?? null,
                'raw_journal_entries' => $tx->journalEntries,
            ];
        });

        return response()->json([
            'summary' => [
                'total_in' => $totalIn,
                'total_out' => $totalOut,
                'net_flow' => $totalIn - $totalOut,
                'period' => [
                    'start' => $startDate ? $startDate->format('Y-m-d') : 'All Time',
                    'end' => $endDate ? $endDate->format('Y-m-d') : 'All Time',
                ]
            ],
            'data' => $formattedTransactions,
        ]);
    }

    /**
     * Store a newly created transaction in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['required', 'in:in,out'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'account_id' => ['required', 'exists:accounts,id'], // Kategori Beban/Pendapatan
            'payment_account_id' => ['required', 'exists:accounts,id'], // Kas Utama / Bank BCA
            'transaction_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
            'receipt' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,pdf', 'max:5120'], // Max 5MB
        ]);

        $date = $request->transaction_date ? Carbon::parse($request->transaction_date) : Carbon::today();
        $userId = $request->user()->id;

        $transaction = DB::transaction(function () use ($request, $date, $userId) {
            // 1. Generate Transaction Number (TX-YYYYMMDD-XXXX)
            $dateStr = $date->format('Ymd');
            $prefix = 'TX-' . $dateStr . '-';
            
            $lastTx = Transaction::where('transaction_number', 'like', $prefix . '%')
                ->orderBy('transaction_number', 'desc')
                ->first();
                
            $nextNumber = 1;
            if ($lastTx) {
                $lastNumStr = substr($lastTx->transaction_number, -4);
                $nextNumber = intval($lastNumStr) + 1;
            }
            $transactionNumber = $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            // 2. Create Transaction Header
            $tx = Transaction::create([
                'transaction_number' => $transactionNumber,
                'transaction_date' => $date,
                'description' => $request->description,
                'created_by' => $userId,
            ]);

            // 3. Post Double Entry Journal
            if ($request->type === 'out') {
                // Cash Outflow:
                // Debit: Expense/Category Account (Beban bertambah)
                JournalEntry::create([
                    'transaction_id' => $tx->id,
                    'account_id' => $request->account_id,
                    'type' => 'debit',
                    'amount' => $request->amount,
                ]);
                // Credit: Asset/Payment Account (Kas/Bank berkurang)
                JournalEntry::create([
                    'transaction_id' => $tx->id,
                    'account_id' => $request->payment_account_id,
                    'type' => 'credit',
                    'amount' => $request->amount,
                ]);
            } else {
                // Cash Inflow:
                // Debit: Asset/Payment Account (Kas/Bank bertambah)
                JournalEntry::create([
                    'transaction_id' => $tx->id,
                    'account_id' => $request->payment_account_id,
                    'type' => 'debit',
                    'amount' => $request->amount,
                ]);
                // Credit: Revenue/Category Account (Pendapatan bertambah)
                JournalEntry::create([
                    'transaction_id' => $tx->id,
                    'account_id' => $request->account_id,
                    'type' => 'credit',
                    'amount' => $request->amount,
                ]);
            }

            // 4. Store Uploaded Receipt
            if ($request->hasFile('receipt')) {
                $file = $request->file('receipt');
                $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('receipts', $filename, 'public');

                Attachment::create([
                    'transaction_id' => $tx->id,
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                ]);
            }

            return $tx;
        });

        // Load relations for response
        $transaction->load(['journalEntries.account', 'attachments']);

        return response()->json([
            'message' => 'Transaction saved successfully.',
            'data' => $transaction,
        ], 201);
    }

    /**
     * Display the specified transaction.
     */
    public function show(int $id): JsonResponse
    {
        $transaction = Transaction::with(['journalEntries.account', 'attachments', 'creator'])
            ->findOrFail($id);

        return response()->json([
            'data' => $transaction,
        ]);
    }

    /**
     * Update the specified transaction.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'type' => ['required', 'in:in,out'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'account_id' => ['required', 'exists:accounts,id'],
            'payment_account_id' => ['required', 'exists:accounts,id'],
            'transaction_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
            'receipt' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,pdf', 'max:5120'], // Max 5MB
        ]);

        $tx = Transaction::findOrFail($id);
        $date = Carbon::parse($request->transaction_date);

        DB::transaction(function () use ($request, $tx, $date) {
            // 1. Update transaction header
            $tx->update([
                'transaction_date' => $date,
                'description' => $request->description,
            ]);

            // 2. Clear old journal entries and recreate them
            JournalEntry::where('transaction_id', $tx->id)->delete();

            if ($request->type === 'out') {
                // Cash Outflow
                JournalEntry::create([
                    'transaction_id' => $tx->id,
                    'account_id' => $request->account_id,
                    'type' => 'debit',
                    'amount' => $request->amount,
                ]);
                JournalEntry::create([
                    'transaction_id' => $tx->id,
                    'account_id' => $request->payment_account_id,
                    'type' => 'credit',
                    'amount' => $request->amount,
                ]);
            } else {
                // Cash Inflow
                JournalEntry::create([
                    'transaction_id' => $tx->id,
                    'account_id' => $request->payment_account_id,
                    'type' => 'debit',
                    'amount' => $request->amount,
                ]);
                JournalEntry::create([
                    'transaction_id' => $tx->id,
                    'account_id' => $request->account_id,
                    'type' => 'credit',
                    'amount' => $request->amount,
                ]);
            }

            // 3. Handle receipt replacement if a new file is uploaded
            if ($request->hasFile('receipt')) {
                // Delete old attachment files
                $oldAttachments = Attachment::where('transaction_id', $tx->id)->get();
                foreach ($oldAttachments as $old) {
                    Storage::disk('public')->delete($old->file_path);
                    $old->delete();
                }

                // Store new file
                $file = $request->file('receipt');
                $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('receipts', $filename, 'public');

                Attachment::create([
                    'transaction_id' => $tx->id,
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                ]);
            }
        });

        // Load relations for response
        $tx->load(['journalEntries.account', 'attachments']);

        return response()->json([
            'message' => 'Transaction updated successfully.',
            'data' => $tx,
        ]);
    }

    /**
     * Remove the specified transaction from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        if (Auth::user()->role !== 'owner') {
            return response()->json([
                'message' => 'Akses Ditolak: Hanya Owner yang dapat menghapus transaksi.',
            ], 403);
        }

        $tx = Transaction::with('attachments')->findOrFail($id);

        DB::transaction(function () use ($tx) {
            // Delete physical receipt files first
            foreach ($tx->attachments as $attachment) {
                Storage::disk('public')->delete($attachment->file_path);
            }

            // Delete transaction (which automatically cascades to journal entries & attachments)
            $tx->delete();
        });

        return response()->json([
            'message' => 'Transaction deleted successfully.',
        ]);
    }
}
