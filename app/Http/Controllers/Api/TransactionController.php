<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
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
use Illuminate\Validation\Rule;

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
            // Cash Inflow: Debit to Asset/Liability account (11 or 21)
            // Cash Outflow: Credit to Asset/Liability account (11 or 21)
            $type = 'unknown';
            $amount = 0;
            $category = null;
            $paymentSource = null;

            foreach ($tx->journalEntries as $entry) {
                $accCode = $entry->account->code;
                $isPaymentSource = Str::startsWith($accCode, '11') || Str::startsWith($accCode, '21');

                if ($isPaymentSource) {
                    $paymentSource = $entry->account->name;
                    $amount = floatval($entry->amount);
                    if ($entry->type === 'debit') {
                        $type = 'in';
                    } else {
                        $type = 'out';
                    }
                } else {
                    $category = $entry->account->name;
                }
            }

            // Fallback amount if no asset/liability account is mapped
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
                'is_reimbursement' => $tx->is_reimbursement ? true : false,
                'reimbursement_status' => $tx->reimbursement_status,
                'transfer_proof_path' => $tx->transfer_proof_path,
                'transfer_proof_url' => $tx->transfer_proof_path ? route('web.attachments.show', ['path' => $tx->transfer_proof_path]) : null,
                'is_advance' => $tx->is_advance ? true : false,
                'advance_status' => $tx->advance_status,
                'settled_at' => $tx->settled_at ? $tx->settled_at->format('Y-m-d H:i:s') : null,
                'settlement_amount' => $tx->settlement_amount,
                'is_loan' => $tx->is_loan ? true : false,
                'loan_status' => $tx->loan_status,
                'loan_repaid_amount' => $tx->loan_repaid_amount,
                'loan_parent_id' => $tx->loan_parent_id,
                'is_transferred' => $tx->is_transferred ? true : false,
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
            'payment_account_id' => [
                Rule::requiredIf(function() use ($request) {
                    $isReimbursement = filter_var($request->is_reimbursement, FILTER_VALIDATE_BOOLEAN);
                    return !$isReimbursement || $request->reimbursement_status === 'transferred';
                }),
                'nullable',
                'exists:accounts,id'
            ],
            'transaction_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
            'receipt' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,pdf', 'max:5120'], // Max 5MB
            'is_reimbursement' => ['nullable', 'boolean'],
            'reimbursement_status' => ['nullable', 'in:pending,transferred'],
            'transfer_proof' => ['nullable', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:5120'],
            'is_transferred' => ['nullable', 'boolean'],
        ]);

        $date = $request->transaction_date ? Carbon::parse($request->transaction_date) : Carbon::today();
        $userId = $request->user()->id;
        $isReimbursement = filter_var($request->is_reimbursement, FILTER_VALIDATE_BOOLEAN);

        $transaction = DB::transaction(function () use ($request, $date, $userId, $isReimbursement) {
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

            // Handle transfer proof path
            $transferProofPath = null;
            if ($isReimbursement && $request->reimbursement_status === 'transferred' && $request->hasFile('transfer_proof')) {
                $file = $request->file('transfer_proof');
                $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
                $transferProofPath = $file->storeAs('receipts', $filename, 'public');
            }

            // Determine transfer status
            // If reimbursement, it is transferred if status is 'transferred'
            // Otherwise, read from is_transferred input parameter (defaulting to true for regular cash entries)
            $isTransferred = $isReimbursement 
                ? ($request->reimbursement_status === 'transferred') 
                : filter_var($request->is_transferred ?? true, FILTER_VALIDATE_BOOLEAN);

            // 2. Create Transaction Header
            $tx = Transaction::create([
                'transaction_number' => $transactionNumber,
                'transaction_date' => $date,
                'description' => $request->description,
                'is_reimbursement' => $isReimbursement,
                'reimbursement_status' => $isReimbursement ? ($request->reimbursement_status ?: 'pending') : null,
                'transfer_proof_path' => $transferProofPath,
                'is_transferred' => $isTransferred,
                'created_by' => $userId,
            ]);

            // 3. Post Double Entry Journal
            if ($request->type === 'out') {
                if ($tx->is_reimbursement && $tx->reimbursement_status === 'pending') {
                    // Pending Reimbursement Jurnal: Debit Kategori Beban, Credit Utang Usaha (2101)
                    $utangAcc = Account::where('code', '2101')->firstOrFail();
                    JournalEntry::create([
                        'transaction_id' => $tx->id,
                        'account_id' => $request->account_id,
                        'type' => 'debit',
                        'amount' => $request->amount,
                    ]);
                    JournalEntry::create([
                        'transaction_id' => $tx->id,
                        'account_id' => $utangAcc->id,
                        'type' => 'credit',
                        'amount' => $request->amount,
                    ]);
                } else {
                    // Cash Outflow: Debit Kategori Beban, Credit Kas/Bank
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
                }
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
            'payment_account_id' => [
                Rule::requiredIf(function() use ($request) {
                    $isReimbursement = filter_var($request->is_reimbursement, FILTER_VALIDATE_BOOLEAN);
                    return !$isReimbursement || $request->reimbursement_status === 'transferred';
                }),
                'nullable',
                'exists:accounts,id'
            ],
            'transaction_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
            'receipt' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,pdf', 'max:5120'], // Max 5MB
            'is_reimbursement' => ['nullable', 'boolean'],
            'reimbursement_status' => ['nullable', 'in:pending,transferred'],
            'transfer_proof' => ['nullable', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:5120'],
            'is_transferred' => ['nullable', 'boolean'],
        ]);

        $tx = Transaction::findOrFail($id);
        $date = Carbon::parse($request->transaction_date);
        $isReimbursement = filter_var($request->is_reimbursement, FILTER_VALIDATE_BOOLEAN);

        DB::transaction(function () use ($request, $tx, $date, $isReimbursement) {
            // Handle transfer proof path
            $transferProofPath = $tx->transfer_proof_path;
            if ($isReimbursement && $request->reimbursement_status === 'transferred' && $request->hasFile('transfer_proof')) {
                // Delete old transfer proof if exists
                if ($tx->transfer_proof_path) {
                    Storage::disk('public')->delete($tx->transfer_proof_path);
                }
                $file = $request->file('transfer_proof');
                $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
                $transferProofPath = $file->storeAs('receipts', $filename, 'public');
            }

            $isTransferred = $isReimbursement 
                ? ($request->reimbursement_status === 'transferred') 
                : filter_var($request->is_transferred ?? true, FILTER_VALIDATE_BOOLEAN);

            // 1. Update transaction header
            $tx->update([
                'transaction_date' => $date,
                'description' => $request->description,
                'is_reimbursement' => $isReimbursement,
                'reimbursement_status' => $isReimbursement ? ($request->reimbursement_status ?: 'pending') : null,
                'transfer_proof_path' => $transferProofPath,
                'is_transferred' => $isTransferred,
            ]);

            // 2. Clear old journal entries and recreate them
            JournalEntry::where('transaction_id', $tx->id)->delete();

            if ($request->type === 'out') {
                if ($tx->is_reimbursement && $tx->reimbursement_status === 'pending') {
                    // Pending Reimbursement Jurnal: Debit Kategori Beban, Credit Utang Usaha (2101)
                    $utangAcc = Account::where('code', '2101')->firstOrFail();
                    JournalEntry::create([
                        'transaction_id' => $tx->id,
                        'account_id' => $request->account_id,
                        'type' => 'debit',
                        'amount' => $request->amount,
                    ]);
                    JournalEntry::create([
                        'transaction_id' => $tx->id,
                        'account_id' => $utangAcc->id,
                        'type' => 'credit',
                        'amount' => $request->amount,
                    ]);
                } else {
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
                }
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
     * Store a new Advance Payment (Uang Muka).
     */
    public function storeAdvance(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_account_id' => ['required', 'exists:accounts,id'],
            'transaction_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:500'],
            'recipient_name' => ['required', 'string', 'max:100'],
            'is_transferred' => ['nullable', 'boolean'],
        ]);

        $date = Carbon::parse($request->transaction_date);

        $transaction = DB::transaction(function () use ($request, $date) {
            $prefix = 'TX-' . $date->format('Ymd') . '-';
            $lastCount = Transaction::where('transaction_number', 'like', $prefix . '%')->count();
            $txNumber = $prefix . str_pad($lastCount + 1, 4, '0', STR_PAD_LEFT);

            $tx = Transaction::create([
                'transaction_number' => $txNumber,
                'transaction_date' => $date,
                'description' => $request->description,
                'recipient_name' => $request->recipient_name,
                'is_advance' => true,
                'advance_status' => 'open',
                'is_transferred' => $request->has('is_transferred') ? filter_var($request->is_transferred, FILTER_VALIDATE_BOOLEAN) : false,
                'created_by' => Auth::id(),
            ]);

            $advanceAccount = Account::where('code', '1202')->first();
            if (!$advanceAccount) {
                $advanceAccount = Account::create(['code' => '1202', 'name' => 'Uang Muka Pembelian', 'type' => 'asset']);
            }

            JournalEntry::create([
                'transaction_id' => $tx->id,
                'account_id' => $advanceAccount->id,
                'type' => 'debit',
                'amount' => $request->amount,
            ]);

            JournalEntry::create([
                'transaction_id' => $tx->id,
                'account_id' => $request->payment_account_id,
                'type' => 'credit',
                'amount' => $request->amount,
            ]);

            return $tx;
        });

        $transaction->load(['journalEntries.account', 'attachments']);

        return response()->json([
            'message' => 'Uang muka berhasil dicatat!',
            'data' => $transaction,
        ], 201);
    }

    /**
     * Settle an outstanding advance payment.
     */
    public function settleAdvance(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'settlement_amount' => ['required', 'numeric', 'min:0.01'],
            'expense_account_id' => ['required', 'exists:accounts,id'],
            'receipt' => ['required', 'file', 'image', 'mimes:jpeg,png,jpg,pdf', 'max:5120'],
        ]);

        $tx = Transaction::findOrFail($id);
        if (!$tx->is_advance || $tx->advance_status !== 'open') {
            return response()->json([
                'message' => 'Uang muka ini tidak dapat diselesaikan atau sudah selesai.',
            ], 422);
        }

        DB::transaction(function () use ($request, $tx) {
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

            $advanceAccount = Account::where('code', '1202')->first();
            $originalEntry = JournalEntry::where('transaction_id', $tx->id)
                ->where('account_id', $advanceAccount->id)
                ->first();
            $originalAmount = floatval($originalEntry->amount);

            $cashEntry = JournalEntry::where('transaction_id', $tx->id)
                ->where('account_id', '!=', $advanceAccount->id)
                ->first();
            $cashAccountId = $cashEntry->account_id;

            $settlementAmount = floatval($request->settlement_amount);

            $tx->update([
                'advance_status' => 'settled',
                'settled_at' => now(),
                'settlement_amount' => $settlementAmount,
            ]);

            JournalEntry::create([
                'transaction_id' => $tx->id,
                'account_id' => $request->expense_account_id,
                'type' => 'debit',
                'amount' => $settlementAmount,
            ]);

            JournalEntry::create([
                'transaction_id' => $tx->id,
                'account_id' => $advanceAccount->id,
                'type' => 'credit',
                'amount' => $originalAmount,
            ]);

            $difference = $settlementAmount - $originalAmount;
            if ($difference > 0) {
                JournalEntry::create([
                    'transaction_id' => $tx->id,
                    'account_id' => $cashAccountId,
                    'type' => 'credit',
                    'amount' => $difference,
                ]);
            } elseif ($difference < 0) {
                JournalEntry::create([
                    'transaction_id' => $tx->id,
                    'account_id' => $cashAccountId,
                    'type' => 'debit',
                    'amount' => abs($difference),
                ]);
            }
        });

        $tx->load(['journalEntries.account', 'attachments']);

        return response()->json([
            'message' => 'Settlement berhasil diproses!',
            'data' => $tx,
        ]);
    }

    /**
     * Store a new Cash Advance loan.
     */
    public function storeLoan(Request $request): JsonResponse
    {
        $request->validate([
            'transaction_date' => 'required|date',
            'recipient_name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'payment_account_id' => 'required|exists:accounts,id',
            'description' => 'required|string|max:255',
            'is_transferred' => 'nullable|boolean',
        ]);

        $amount = floatval($request->amount);

        $loanAccount = Account::where('code', '1203')->first();
        if (!$loanAccount) {
            return response()->json([
                'message' => 'Akun Piutang Karyawan (1203) belum terdaftar.',
            ], 422);
        }

        $transaction = DB::transaction(function () use ($request, $amount, $loanAccount) {
            $datePrefix = Carbon::parse($request->transaction_date)->format('Ymd');
            $countToday = Transaction::whereDate('transaction_date', $request->transaction_date)
                ->where('transaction_number', 'LIKE', "CA-{$datePrefix}-%")
                ->count();
            $seq = str_pad($countToday + 1, 4, '0', STR_PAD_LEFT);
            $txNum = "CA-{$datePrefix}-{$seq}";

            $tx = Transaction::create([
                'transaction_number' => $txNum,
                'transaction_date' => $request->transaction_date,
                'description' => $request->description,
                'recipient_name' => $request->recipient_name,
                'is_advance' => false,
                'is_loan' => true,
                'loan_status' => 'open',
                'loan_repaid_amount' => 0,
                'is_transferred' => $request->has('is_transferred') ? filter_var($request->is_transferred, FILTER_VALIDATE_BOOLEAN) : false,
                'created_by' => Auth::id(),
            ]);

            JournalEntry::create([
                'transaction_id' => $tx->id,
                'account_id' => $loanAccount->id,
                'type' => 'debit',
                'amount' => $amount,
            ]);

            JournalEntry::create([
                'transaction_id' => $tx->id,
                'account_id' => $request->payment_account_id,
                'type' => 'credit',
                'amount' => $amount,
            ]);

            return $tx;
        });

        $transaction->load(['journalEntries.account', 'attachments']);

        return response()->json([
            'message' => 'Pinjaman berhasil dicatat!',
            'data' => $transaction,
        ], 201);
    }

    /**
     * Store a loan repayment (angsuran).
     */
    public function storeRepayment(Request $request, int $id): JsonResponse
    {
        $loan = Transaction::where('is_loan', true)->findOrFail($id);
        if ($loan->loan_status === 'repaid') {
            return response()->json([
                'message' => 'Pinjaman ini sudah lunas.',
            ], 422);
        }

        $request->validate([
            'transaction_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_account_id' => 'required|exists:accounts,id',
            'description' => 'required|string|max:255',
        ]);

        $amount = floatval($request->amount);

        $loanAmount = 0;
        foreach ($loan->journalEntries as $entry) {
            if (Str::startsWith($entry->account->code, '1203') && $entry->type === 'debit') {
                $loanAmount = floatval($entry->amount);
            }
        }
        if ($loanAmount === 0 && $loan->journalEntries->isNotEmpty()) {
            $loanAmount = floatval($loan->journalEntries->first()->amount);
        }

        $currentRepaid = floatval($loan->loan_repaid_amount);
        $remaining = $loanAmount - $currentRepaid;

        if ($amount > $remaining) {
            return response()->json([
                'message' => 'Nominal angsuran tidak boleh melebihi sisa pinjaman (Rp ' . number_format($remaining, 0, ',', '.') . ').',
            ], 422);
        }

        $loanAccount = Account::where('code', '1203')->first();
        if (!$loanAccount) {
            return response()->json([
                'message' => 'Akun Piutang Karyawan (1203) belum terdaftar.',
            ], 422);
        }

        $repayment = DB::transaction(function () use ($loan, $request, $amount, $loanAccount, $currentRepaid, $loanAmount) {
            $datePrefix = Carbon::parse($request->transaction_date)->format('Ymd');
            $countToday = Transaction::whereDate('transaction_date', $request->transaction_date)
                ->where('transaction_number', 'LIKE', "CAR-{$datePrefix}-%")
                ->count();
            $seq = str_pad($countToday + 1, 4, '0', STR_PAD_LEFT);
            $txNum = "CAR-{$datePrefix}-{$seq}";

            $repTx = Transaction::create([
                'transaction_number' => $txNum,
                'transaction_date' => $request->transaction_date,
                'description' => $request->description,
                'recipient_name' => $loan->recipient_name,
                'is_advance' => false,
                'is_loan' => false,
                'loan_parent_id' => $loan->id,
                'created_by' => Auth::id(),
            ]);

            JournalEntry::create([
                'transaction_id' => $repTx->id,
                'account_id' => $request->payment_account_id,
                'type' => 'debit',
                'amount' => $amount,
            ]);

            JournalEntry::create([
                'transaction_id' => $repTx->id,
                'account_id' => $loanAccount->id,
                'type' => 'credit',
                'amount' => $amount,
            ]);

            $newRepaid = $currentRepaid + $amount;
            $loan->update([
                'loan_repaid_amount' => $newRepaid,
                'loan_status' => ($newRepaid >= $loanAmount) ? 'repaid' : 'open',
            ]);

            return $repTx;
        });

        $repayment->load(['journalEntries.account', 'attachments']);

        return response()->json([
            'message' => 'Pembayaran angsuran berhasil dicatat!',
            'data' => $repayment,
        ], 201);
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

    /**
     * Get General Ledger (Buku Besar) report for a specific account and date range.
     */
    public function ledger(Request $request): JsonResponse
    {
        $request->validate([
            'account_id' => ['required', 'exists:accounts,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $accountId = $request->account_id;
        $startDate = $request->start_date ?: Carbon::now()->startOfMonth()->format('Y-m-d');
        $endDate = $request->end_date ?: Carbon::now()->endOfMonth()->format('Y-m-d');

        $account = Account::findOrFail($accountId);
        $startCarbon = Carbon::parse($startDate)->startOfDay();
        $endCarbon = Carbon::parse($endDate)->endOfDay();

        // 1. Calculate Starting Balance (Saldo Awal)
        $startingBalance = 0;
        $priorEntries = JournalEntry::where('account_id', $accountId)
            ->whereHas('transaction', function($q) use ($startCarbon) {
                $q->where('transaction_date', '<', $startCarbon);
            })->get();

        foreach ($priorEntries as $entry) {
            $amount = floatval($entry->amount);
            if ($account->type === 'asset' || $account->type === 'expense') {
                if ($entry->type === 'debit') {
                    $startingBalance += $amount;
                } else {
                    $startingBalance -= $amount;
                }
            } else {
                if ($entry->type === 'credit') {
                    $startingBalance += $amount;
                } else {
                    $startingBalance -= $amount;
                }
            }
        }

        // 2. Fetch Mutation Entries
        $rawEntries = JournalEntry::with(['transaction.creator'])
            ->where('account_id', $accountId)
            ->whereHas('transaction', function($q) use ($startCarbon, $endCarbon) {
                $q->whereBetween('transaction_date', [$startCarbon, $endCarbon]);
            })->get();

        $sortedEntries = $rawEntries->sortBy(function($entry) {
            return $entry->transaction->transaction_date->format('Y-m-d') . '_' . str_pad($entry->transaction_id, 10, '0', STR_PAD_LEFT);
        });

        $runningBalance = $startingBalance;
        $formattedEntries = $sortedEntries->map(function ($entry) use (&$runningBalance, $account) {
            $amount = floatval($entry->amount);
            $debit = 0;
            $credit = 0;

            if ($entry->type === 'debit') {
                $debit = $amount;
                if ($account->type === 'asset' || $account->type === 'expense') {
                    $runningBalance += $amount;
                } else {
                    $runningBalance -= $amount;
                }
            } else {
                $credit = $amount;
                if ($account->type === 'asset' || $account->type === 'expense') {
                    $runningBalance -= $amount;
                } else {
                    $runningBalance += $amount;
                }
            }

            return [
                'id' => $entry->id,
                'transaction_id' => $entry->transaction_id,
                'transaction_number' => $entry->transaction->transaction_number,
                'transaction_date' => $entry->transaction->transaction_date->format('Y-m-d'),
                'description' => $entry->transaction->description,
                'creator' => $entry->transaction->creator->name ?? 'System',
                'debit' => $debit,
                'credit' => $credit,
                'running_balance' => $runningBalance
            ];
        })->values();

        return response()->json([
            'success' => true,
            'account' => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
            ],
            'start_date' => $startDate,
            'end_date' => $endDate,
            'starting_balance' => $startingBalance,
            'ending_balance' => $runningBalance,
            'entries' => $formattedEntries,
        ]);
    }

    /**
     * Mark a specific transaction as transferred.
     */
    public function markTransferred(Request $request, int $id): JsonResponse
    {
        $tx = Transaction::findOrFail($id);
        $tx->is_transferred = true;
        
        if ($tx->is_reimbursement) {
            $tx->reimbursement_status = 'transferred';
        }
        
        $tx->save();

        return response()->json([
            'success' => true,
            'message' => 'Transaksi berhasil ditandai sudah ditransfer.',
            'data' => $tx,
        ]);
    }
}

