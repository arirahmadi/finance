<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Attachment;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WebController extends Controller
{
    /**
     * Show login form.
     */
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return view('auth.login');
    }

    /**
     * Handle login authentication.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'email' => 'Kredensial yang diberikan salah atau tidak cocok.',
        ])->onlyInput('email');
    }

    /**
     * Handle logout.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    /**
     * Show main dashboard.
     */
    public function index(Request $request): View
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = Transaction::with(['journalEntries.account', 'attachments', 'creator'])
            ->where('is_advance', false)
            ->where('is_loan', false)
            ->whereNull('loan_parent_id')
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc');

        if ($startDate) {
            $query->where('transaction_date', '>=', Carbon::parse($startDate)->startOfDay());
        }
        if ($endDate) {
            $query->where('transaction_date', '<=', Carbon::parse($endDate)->endOfDay());
        }

        $transactions = $query->get();

        $totalIn = 0;
        $totalOut = 0;
        $totalOutTransferred = 0;
        $totalOutEstimated = 0;

        $formattedTransactions = $transactions->map(function ($tx) use (&$totalIn, &$totalOut, &$totalOutTransferred, &$totalOutEstimated) {
            $type = 'unknown';
            $amount = 0;
            $category = null;
            $categoryId = null;
            $paymentSource = null;
            $paymentAccountId = null;

            foreach ($tx->journalEntries as $entry) {
                $accCode = $entry->account->code;
                $isPaymentSource = Str::startsWith($accCode, '11') || Str::startsWith($accCode, '21');

                if ($isPaymentSource) {
                    $paymentSource = $entry->account->name;
                    $paymentAccountId = $entry->account_id;
                    $amount = floatval($entry->amount);
                    if ($entry->type === 'debit') {
                        $type = 'in';
                    } else {
                        $type = 'out';
                    }
                } else {
                    $category = $entry->account->name;
                    $categoryId = $entry->account_id;
                }
            }

            if ($amount === 0 && $tx->journalEntries->isNotEmpty()) {
                $amount = floatval($tx->journalEntries->first()->amount);
            }

            $isTransferredValue = $tx->is_reimbursement
                ? ($tx->reimbursement_status === 'transferred')
                : ($tx->is_transferred ? true : false);

            if ($type === 'in') {
                $totalIn += $amount;
            } elseif ($type === 'out') {
                $totalOut += $amount;
                if ($isTransferredValue) {
                    $totalOutTransferred += $amount;
                } else {
                    $totalOutEstimated += $amount;
                }
            }

            return (object) [
                'id' => $tx->id,
                'transaction_number' => $tx->transaction_number,
                'transaction_date' => $tx->transaction_date,
                'description' => $tx->description,
                'type' => $type,
                'amount' => $amount,
                'category' => $category,
                'category_id' => $categoryId,
                'payment_source' => $paymentSource,
                'payment_account_id' => $paymentAccountId,
                'is_reimbursement' => $tx->is_reimbursement,
                'reimbursement_status' => $tx->reimbursement_status,
                'transfer_proof_path' => $tx->transfer_proof_path,
                'transfer_proof_url' => $tx->transfer_proof_path ? route('web.attachments.show', ['path' => $tx->transfer_proof_path]) : null,
                'attachments' => $tx->attachments,
                'creator' => $tx->creator->name ?? null,
                'is_transferred' => $isTransferredValue,
            ];
        });

        // ── CATEGORY WISE SUMMARY FOR TRANSACTIONS TAB ──
        $categorySummaryData = [];
        foreach ($formattedTransactions as $tx) {
            $catName = $tx->category ?? 'Lainnya';
            if (!isset($categorySummaryData[$catName])) {
                $categorySummaryData[$catName] = (object) [
                    'category' => $catName,
                    'uang_masuk' => 0.0,
                    'uang_keluar_transfer' => 0.0,
                    'uang_keluar_prakiraan' => 0.0,
                ];
            }
            if ($tx->type === 'in') {
                $categorySummaryData[$catName]->uang_masuk += $tx->amount;
            } elseif ($tx->type === 'out') {
                if ($tx->is_transferred) {
                    $categorySummaryData[$catName]->uang_keluar_transfer += $tx->amount;
                } else {
                    $categorySummaryData[$catName]->uang_keluar_prakiraan += $tx->amount;
                }
            }
        }
        $categorySummary = collect($categorySummaryData)->sortBy('category')->values();


        // Load Chart of Accounts for Dropdowns
        $allAccounts = Account::orderBy('code')->get();
        $paymentAccounts = $allAccounts->filter(fn($acc) => Str::startsWith($acc->code, '11'));
        $expenseAccounts = $allAccounts->filter(fn($acc) => Str::startsWith($acc->code, '51'));
        $revenueAccounts = $allAccounts->filter(fn($acc) => Str::startsWith($acc->code, '41'));

        // Load users for User & Roles tab
        $users = collect();
        if (Auth::user()->role === 'owner') {
            $users = User::where('id', '!=', Auth::id())->orderBy('id', 'desc')->get();
        }

        // Load advances for Settlements tab
        $advances = Transaction::with(['journalEntries.account', 'attachments', 'creator'])
            ->where('is_advance', true)
            ->orderBy('transaction_date', 'desc')
            ->get();

        $totalOutstanding = 0;
        $totalSettled = 0;
        $formattedAdvances = $advances->map(function ($tx) use (&$totalOutstanding, &$totalSettled) {
            $amount = 0;
            $paymentSource = '';
            $paymentAccountId = null;
            $expenseAccountId = null;
            foreach ($tx->journalEntries as $entry) {
                if (Str::startsWith($entry->account->code, '11') && $entry->type === 'credit') {
                    $paymentSource = $entry->account->name;
                    $paymentAccountId = $entry->account_id;
                    $amount = floatval($entry->amount);
                }
                if (Str::startsWith($entry->account->code, '51') && $entry->type === 'debit') {
                    $expenseAccountId = $entry->account_id;
                }
            }

            if ($amount === 0 && $tx->journalEntries->isNotEmpty()) {
                $amount = floatval($tx->journalEntries->first()->amount);
            }

            if ($tx->advance_status === 'open') {
                $totalOutstanding += $amount;
            } else {
                $totalSettled += $amount;
            }

            $settlementAttachment = null;
            if ($tx->advance_status === 'settled') {
                $settlementAttachment = $tx->attachments->first();
            }

            return (object) [
                'id' => $tx->id,
                'transaction_number' => $tx->transaction_number,
                'transaction_date' => $tx->transaction_date,
                'description' => $tx->description,
                'amount' => $amount,
                'payment_source' => $paymentSource,
                'payment_account_id' => $paymentAccountId,
                'advance_status' => $tx->advance_status,
                'settled_at' => $tx->settled_at,
                'settlement_amount' => floatval($tx->settlement_amount),
                'attachment' => $settlementAttachment,
                'creator' => $tx->creator->name ?? null,
                'recipient_name' => $tx->recipient_name,
                'is_transferred' => $tx->is_transferred,
                'expense_account_id' => $expenseAccountId,
            ];
        });

        $settlementSummary = (object) [
            'total_outstanding' => $totalOutstanding,
            'total_settled' => $totalSettled,
        ];

        // Load Cash Advances (Pinjaman Karyawan)
        $loans = Transaction::with(['journalEntries.account', 'creator', 'repayments.journalEntries.account', 'repayments.creator'])
            ->where('is_loan', true)
            ->orderBy('transaction_date', 'desc')
            ->get();

        $totalOutstandingLoans = 0;
        $totalRepaidLoans = 0;
        $totalLoanTransferred = 0;
        $totalLoanEstimated = 0;

        $formattedLoans = $loans->map(function ($tx) use (&$totalOutstandingLoans, &$totalRepaidLoans, &$totalLoanTransferred, &$totalLoanEstimated) {
            // Find loan amount from header first, fallback to 1203 entry (debit)
            $amount = floatval($tx->amount ?? 0);
            if ($amount == 0) {
                foreach ($tx->journalEntries as $entry) {
                    if (Str::startsWith($entry->account->code, '1203') && $entry->type === 'debit') {
                        $amount = floatval($entry->amount);
                    }
                }
            }

            if ($amount === 0 && $tx->journalEntries->isNotEmpty()) {
                $amount = floatval($tx->journalEntries->first()->amount);
            }

            if ($tx->is_transferred) {
                $totalLoanTransferred += floatval($tx->transferred_amount ?: $amount);
            } else {
                $totalLoanEstimated += $amount;
            }

            $paymentSource = '';
            $paymentAccountId = null;
            foreach ($tx->journalEntries as $entry) {
                if (Str::startsWith($entry->account->code, '11') && $entry->type === 'credit') {
                    $paymentSource = $entry->account->name;
                    $paymentAccountId = $entry->account_id;
                }
            }

            // If loan is transferred, remaining is calculated based on transferred_amount (or amount if not set)
            $baseAmount = $tx->is_transferred ? floatval($tx->transferred_amount ?: $amount) : $amount;
            $repaidAmount = floatval($tx->loan_repaid_amount);
            $remainingAmount = $baseAmount - $repaidAmount;

            if ($tx->loan_status === 'repaid') {
                $totalRepaidLoans += $amount;
            } else {
                if ($tx->is_transferred) {
                    $totalOutstandingLoans += $remainingAmount;
                }
            }

            // Repayments for this loan
            $repayments = Transaction::with(['journalEntries.account', 'creator'])
                ->where('loan_parent_id', $tx->id)
                ->orderBy('transaction_date', 'asc')
                ->get()
                ->map(function ($rep) {
                    $repAmount = 0;
                    $destAccount = '';
                    foreach ($rep->journalEntries as $entry) {
                        if (Str::startsWith($entry->account->code, '11') && $entry->type === 'debit') {
                            $repAmount = floatval($entry->amount);
                            $destAccount = $entry->account->name;
                        }
                    }
                    if ($repAmount === 0 && $rep->journalEntries->isNotEmpty()) {
                        $repAmount = floatval($rep->journalEntries->first()->amount);
                    }
                    return (object) [
                        'id' => $rep->id,
                        'transaction_number' => $rep->transaction_number,
                        'transaction_date' => $rep->transaction_date,
                        'description' => $rep->description,
                        'amount' => $repAmount,
                        'destination_account' => $destAccount,
                        'creator' => $rep->creator->name ?? null,
                    ];
                });

            return (object) [
                'id' => $tx->id,
                'transaction_number' => $tx->transaction_number,
                'transaction_date' => $tx->transaction_date,
                'description' => $tx->description,
                'amount' => $amount,
                'payment_source' => $paymentSource,
                'payment_account_id' => $paymentAccountId,
                'loan_status' => $tx->loan_status,
                'loan_repaid_amount' => $repaidAmount,
                'remaining_amount' => $remainingAmount,
                'recipient_name' => $tx->recipient_name,
                'creator' => $tx->creator->name ?? null,
                'repayments' => $repayments,
                'is_transferred' => $tx->is_transferred,
                'transferred_amount' => floatval($tx->transferred_amount),
            ];
        });

        $loanSummary = (object) [
            'total_outstanding' => $totalOutstandingLoans,
            'total_repaid' => $totalRepaidLoans,
            'total_transferred' => $totalLoanTransferred,
            'total_estimated' => $totalLoanEstimated,
        ];

        // ── LOAD GENERAL LEDGER (BUKU BESAR) DATA ──
        $ledgerAccountId = $request->input('ledger_account_id');
        $ledgerStartDate = $request->input('ledger_start_date', $startDate ?? Carbon::now()->startOfMonth()->format('Y-m-d'));
        $ledgerEndDate = $request->input('ledger_end_date', $endDate ?? Carbon::now()->endOfMonth()->format('Y-m-d'));
        
        $ledgerAccount = null;
        $ledgerStartingBalance = 0;
        $ledgerEntries = collect([]);
        $ledgerEndingBalance = 0;
        
        if ($ledgerAccountId) {
            $ledgerAccount = Account::find($ledgerAccountId);
            if ($ledgerAccount) {
                $startCarbon = Carbon::parse($ledgerStartDate)->startOfDay();
                
                // Prior balance calculation (Saldo Awal)
                $priorEntries = JournalEntry::where('account_id', $ledgerAccountId)
                    ->whereHas('transaction', function($q) use ($startCarbon) {
                        $q->where('transaction_date', '<', $startCarbon);
                    })->get();
                    
                foreach ($priorEntries as $entry) {
                    $amount = floatval($entry->amount);
                    if ($ledgerAccount->type === 'asset' || $ledgerAccount->type === 'expense') {
                        if ($entry->type === 'debit') {
                            $ledgerStartingBalance += $amount;
                        } else {
                            $ledgerStartingBalance -= $amount;
                        }
                    } else {
                        if ($entry->type === 'credit') {
                            $ledgerStartingBalance += $amount;
                        } else {
                            $ledgerStartingBalance -= $amount;
                        }
                    }
                }
                
                // Mutation entries calculation
                $endCarbon = Carbon::parse($ledgerEndDate)->endOfDay();
                $rawEntries = JournalEntry::with(['transaction.creator', 'transaction'])
                    ->where('account_id', $ledgerAccountId)
                    ->whereHas('transaction', function($q) use ($startCarbon, $endCarbon) {
                        $q->whereBetween('transaction_date', [$startCarbon, $endCarbon]);
                    })->get();
                
                $sortedEntries = $rawEntries->sortBy(function($entry) {
                    return $entry->transaction->transaction_date->format('Y-m-d') . '_' . str_pad($entry->transaction_id, 10, '0', STR_PAD_LEFT);
                });
                
                $runningBalance = $ledgerStartingBalance;
                $ledgerEntries = $sortedEntries->map(function ($entry) use (&$runningBalance, $ledgerAccount) {
                    $amount = floatval($entry->amount);
                    $debit = 0;
                    $credit = 0;
                    
                    if ($entry->type === 'debit') {
                        $debit = $amount;
                        if ($ledgerAccount->type === 'asset' || $ledgerAccount->type === 'expense') {
                            $runningBalance += $amount;
                        } else {
                            $runningBalance -= $amount;
                        }
                    } else {
                        $credit = $amount;
                        if ($ledgerAccount->type === 'asset' || $ledgerAccount->type === 'expense') {
                            $runningBalance -= $amount;
                        } else {
                            $runningBalance += $amount;
                        }
                    }
                    
                    return (object) [
                        'id' => $entry->id,
                        'transaction_id' => $entry->transaction_id,
                        'transaction_number' => $entry->transaction->transaction_number,
                        'transaction_date' => $entry->transaction->transaction_date,
                        'description' => $entry->transaction->description,
                        'creator' => $entry->transaction->creator->name ?? 'System',
                        'debit' => $debit,
                        'credit' => $credit,
                        'running_balance' => $runningBalance
                    ];
                });
                
                $ledgerEndingBalance = $runningBalance;
            }
        }

        // ── DASHBOARD WIDGETS: Transferred-Only Summary ──
        // 1. Regular Transactions (is_transferred = true)
        $widgetTxIn = 0;
        $widgetTxOut = 0;
        $widgetTxCount = 0;
        foreach ($formattedTransactions as $tx) {
            if (!$tx->is_transferred) continue;
            $widgetTxCount++;
            if ($tx->type === 'in') {
                $widgetTxIn += $tx->amount;
            } elseif ($tx->type === 'out') {
                $widgetTxOut += $tx->amount;
            }
        }

        // 2. Settlements (is_transferred = true)
        $widgetSettlementTotal = 0;
        $widgetSettlementCount = 0;
        foreach ($formattedAdvances as $adv) {
            if (!$adv->is_transferred) continue;
            $widgetSettlementCount++;
            $widgetSettlementTotal += $adv->amount;
        }

        // 3. Cash Advances (is_transferred = true) — uses transferred_amount
        $widgetCaTotal = 0;
        $widgetCaCount = 0;
        foreach ($formattedLoans as $loan) {
            if (!$loan->is_transferred) continue;
            $widgetCaCount++;
            // Use transferred_amount if available, otherwise fall back to amount
            $widgetCaTotal += ($loan->transferred_amount > 0) ? $loan->transferred_amount : $loan->amount;
        }

        $dashboardWidgets = (object) [
            'transactions' => (object) [
                'count' => $widgetTxCount,
                'total_in' => $widgetTxIn,
                'total_out' => $widgetTxOut,
            ],
            'settlements' => (object) [
                'count' => $widgetSettlementCount,
                'total' => $widgetSettlementTotal,
            ],
            'cash_advances' => (object) [
                'count' => $widgetCaCount,
                'total' => $widgetCaTotal,
            ],
        ];

        $totalOutCombined = $widgetTxOut + $widgetSettlementTotal + $widgetCaTotal;

        // Try to get employees - graceful fallback if emp_master table not yet migrated
        try {
            $employees = Employee::orderBy('employee_no', 'asc')->get();
        } catch (\Exception $e) {
            $employees = collect([]);
        }

        return view('dashboard', [
            'transactions' => $formattedTransactions,
            'summary' => (object) [
                'total_in' => $totalIn,
                'total_out' => $totalOutCombined,
                'total_out_transferred' => $totalOutTransferred,
                'total_out_estimated' => $totalOutEstimated,
                'net_flow' => $totalIn - $totalOutCombined,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'paymentAccounts' => $paymentAccounts,
            'expenseAccounts' => $expenseAccounts,
            'revenueAccounts' => $revenueAccounts,
            'allAccounts' => $allAccounts,
            'users' => $users,
            'advances' => $formattedAdvances,
            'settlementSummary' => $settlementSummary,
            'loans' => $formattedLoans,
            'loanSummary' => $loanSummary,
            'activeTab' => $request->input('activeTab', 'dashboard'),
            // Ledger variables
            'ledgerAccount' => $ledgerAccount,
            'ledgerStartingBalance' => $ledgerStartingBalance,
            'ledgerEntries' => $ledgerEntries,
            'ledgerEndingBalance' => $ledgerEndingBalance,
            'ledger_start_date' => $ledgerStartDate,
            'ledger_end_date' => $ledgerEndDate,
            'ledger_account_id' => $ledgerAccountId,
            'dashboardWidgets' => $dashboardWidgets,
            'categorySummary' => $categorySummary,
            'employees' => $employees,
        ]);
    }

    /**
     * Store new transaction via Web form.
     */
    public function storeTransaction(Request $request): RedirectResponse
    {
        if (!Auth::user()->hasPermission('create_transactions')) {
            return back()->withErrors(['auth' => 'Anda tidak memiliki hak akses untuk membuat transaksi.']);
        }

        $request->validate([
            'type' => ['required', 'in:in,out'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'account_id' => ['required', 'exists:accounts,id'],
            'payment_account_id' => [\Illuminate\Validation\Rule::requiredIf(fn() => !$request->is_reimbursement || $request->reimbursement_status === 'transferred'), 'nullable', 'exists:accounts,id'],
            'transaction_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
            'receipt' => ['nullable', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:5120'], // Max 5MB
            'is_reimbursement' => ['nullable', 'boolean'],
            'reimbursement_status' => ['nullable', 'in:pending,transferred'],
            'transfer_proof' => ['nullable', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:5120'],
        ]);

        $date = Carbon::parse($request->transaction_date);
        $userId = Auth::id();

        DB::transaction(function () use ($request, $date, $userId) {
            // Generate Transaction Number (TX-YYYYMMDD-XXXX)
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
            $isTransferred = $request->is_reimbursement 
                ? ($request->reimbursement_status === 'transferred') 
                : $request->has('is_transferred');

            if ($isTransferred && $request->hasFile('transfer_proof')) {
                $file = $request->file('transfer_proof');
                $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
                $transferProofPath = $file->storeAs('receipts', $filename, 'public');
            }

            // Create Transaction
            $tx = Transaction::create([
                'transaction_number' => $transactionNumber,
                'transaction_date' => $date,
                'description' => $request->description,
                'is_reimbursement' => $request->is_reimbursement ? true : false,
                'reimbursement_status' => $request->is_reimbursement ? ($request->reimbursement_status ?: 'pending') : null,
                'transfer_proof_path' => $transferProofPath,
                'created_by' => $userId,
                'is_transferred' => $isTransferred,
            ]);

            // Post Jurnal Double Entry
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
                    // Cash Outflow (Normal or Transferred Reimbursement)
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

            // Save receipt attachment
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
        });

        return back()->with('success', 'Transaksi berhasil disimpan!');
    }

    /**
     * Export transactions to CSV.
     */
    public function exportCsv(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = Transaction::with(['journalEntries.account', 'attachments', 'creator'])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc');

        if ($startDate) {
            $query->where('transaction_date', '>=', Carbon::parse($startDate)->startOfDay());
        }
        if ($endDate) {
            $query->where('transaction_date', '<=', Carbon::parse($endDate)->endOfDay());
        }

        $transactions = $query->get();

        $filename = "laporan_keuangan_" . now()->format('YmdHis') . ".csv";

        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use ($transactions) {
            $file = fopen('php://output', 'w');
            
            // Add UTF-8 BOM for Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Write CSV headers
            fputcsv($file, ['Nomor Bukti', 'Tanggal', 'Jenis', 'Kategori Akun', 'Kas/Bank', 'Deskripsi/Keterangan', 'Nominal (Rp)', 'Petugas']);

            foreach ($transactions as $tx) {
                $type = 'unknown';
                $amount = 0;
                $category = null;
                $paymentSource = null;

                foreach ($tx->journalEntries as $entry) {
                    $accCode = $entry->account->code;
                    $isAsset = Str::startsWith($accCode, '11');

                    if ($isAsset) {
                        $paymentSource = $entry->account->name;
                        $amount = floatval($entry->amount);
                        if ($entry->type === 'debit') {
                            $type = 'Uang Masuk';
                        } else {
                            $type = 'Uang Keluar';
                        }
                    } else {
                        $category = $entry->account->name;
                    }
                }

                if ($amount === 0 && $tx->journalEntries->isNotEmpty()) {
                    $amount = floatval($tx->journalEntries->first()->amount);
                }

                fputcsv($file, [
                    $tx->transaction_number,
                    $tx->transaction_date->format('d-m-Y'),
                    $type,
                    $category,
                    $paymentSource,
                    $tx->description ?? '-',
                    $amount,
                    $tx->creator->name ?? '-'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Update the specified transaction in storage.
     */
    public function editTransaction(Request $request, int $id): RedirectResponse
    {
        if (!Auth::user()->hasPermission('edit_transactions')) {
            return back()->withErrors(['auth' => 'Akses Ditolak: Anda tidak memiliki izin untuk mengedit transaksi.']);
        }

        $request->validate([
            'type' => ['required', 'in:in,out'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'account_id' => ['required', 'exists:accounts,id'],
            'payment_account_id' => [\Illuminate\Validation\Rule::requiredIf(fn() => !$request->is_reimbursement || $request->reimbursement_status === 'transferred'), 'nullable', 'exists:accounts,id'],
            'transaction_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
            'receipt' => ['nullable', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:5120'], // Max 5MB
            'is_reimbursement' => ['nullable', 'boolean'],
            'reimbursement_status' => ['nullable', 'in:pending,transferred'],
            'transfer_proof' => ['nullable', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:5120'],
        ]);

        $tx = Transaction::findOrFail($id);
        $date = Carbon::parse($request->transaction_date);

        DB::transaction(function () use ($request, $tx, $date) {
            // Manage transfer proof file replacement
            $isTransferred = $request->is_reimbursement 
                ? ($request->reimbursement_status === 'transferred') 
                : $request->has('is_transferred');

            $transferProofPath = $tx->transfer_proof_path;
            if ($isTransferred) {
                if ($request->hasFile('transfer_proof')) {
                    if ($tx->transfer_proof_path) {
                        Storage::disk('public')->delete($tx->transfer_proof_path);
                    }
                    $file = $request->file('transfer_proof');
                    $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
                    $transferProofPath = $file->storeAs('receipts', $filename, 'public');
                }
            } else {
                // Changing to pending/not transferred: clear transfer proof file
                if ($tx->transfer_proof_path) {
                    Storage::disk('public')->delete($tx->transfer_proof_path);
                    $transferProofPath = null;
                }
            }

            // 1. Update transaction header
            $tx->update([
                'transaction_date' => $date,
                'description' => $request->description,
                'is_reimbursement' => $request->is_reimbursement ? true : false,
                'reimbursement_status' => $request->is_reimbursement ? ($request->reimbursement_status ?: 'pending') : null,
                'transfer_proof_path' => $transferProofPath,
                'is_transferred' => $isTransferred,
            ]);

            // 2. Clear old journal entries and recreate them
            JournalEntry::where('transaction_id', $tx->id)->delete();

            if ($request->type === 'out') {
                if ($tx->is_reimbursement && $tx->reimbursement_status === 'pending') {
                    // Debit category, Credit Utang Usaha (2101)
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
                    // Normal Outflow or Transferred Reimbursement: Debit category, Credit Kas/Bank
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
                // Cash Inflow: Debit Kas/Bank, Credit category
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

        return back()->with('success', 'Transaksi berhasil diperbarui!');
    }

    /**
     * Remove the specified transaction from storage.
     */
    public function deleteTransaction(Request $request, int $id): RedirectResponse
    {
        if (!Auth::user()->hasPermission('delete_transactions')) {
            return back()->withErrors(['auth' => 'Akses Ditolak: Anda tidak memiliki izin untuk menghapus transaksi.']);
        }

        try {
            $tx = Transaction::with('attachments')->findOrFail($id);

            DB::transaction(function () use ($tx) {
                // Delete physical receipt files safely
                foreach ($tx->attachments as $attachment) {
                    try {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($attachment->file_path);
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning("Failed to delete attachment file: " . $e->getMessage());
                    }
                }

                // Delete transaction (cascades to journal entries & attachments)
                $tx->delete();
            });

            return redirect()->route('dashboard', ['activeTab' => $request->input('activeTab', 'transactions')])
                ->with('success', 'Transaksi berhasil dihapus!');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Delete transaction failed: " . $e->getMessage());
            return back()->withErrors(['delete_error' => 'Gagal menghapus transaksi #' . $id . ': ' . $e->getMessage()]);
        }
    }

    /**
     * Remove multiple transactions from storage.
     */
    public function bulkDeleteTransaction(Request $request): RedirectResponse
    {
        if (!Auth::user()->hasPermission('delete_transactions')) {
            return back()->withErrors(['auth' => 'Akses Ditolak: Anda tidak memiliki izin untuk menghapus transaksi.']);
        }

        $ids = $request->input('ids');
        if (empty($ids) || !is_array($ids)) {
            return back()->withErrors(['bulk' => 'Pilih setidaknya satu transaksi untuk dihapus.']);
        }

        DB::transaction(function () use ($ids) {
            $transactions = Transaction::with('attachments')->whereIn('id', $ids)->get();

            foreach ($transactions as $tx) {
                // Delete physical receipt files
                foreach ($tx->attachments as $attachment) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($attachment->file_path);
                }
                // Delete transaction (which automatically cascades to journal entries & attachments)
                $tx->delete();
            }
        });

        return redirect()->route('dashboard', ['activeTab' => $request->input('activeTab', 'transactions')])->with('success', 'Transaksi terpilih berhasil dihapus!');
    }

    /**
     * Display a listing of users for settings.
     */
    public function indexUsers(): RedirectResponse
    {
        if (Auth::user()->role !== 'owner') {
            return redirect()->route('dashboard')->withErrors(['auth' => 'Akses Ditolak: Hanya Owner yang dapat mengelola pengguna.']);
        }

        return redirect()->route('dashboard', ['activeTab' => 'users']);
    }

    /**
     * Store a newly created user in storage.
     */
    public function storeUser(Request $request): RedirectResponse
    {
        if (Auth::user()->role !== 'owner') {
            return back()->withErrors(['auth' => 'Akses Ditolak: Hanya Owner yang dapat menambah pengguna.']);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'in:owner,staff'],
            'permissions' => ['nullable', 'array'],
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'role' => $request->role,
            'permissions' => $request->permissions ?? [],
        ]);

        return redirect()->route('dashboard', ['activeTab' => 'users'])->with('success', 'User baru berhasil ditambahkan!');
    }

    /**
     * Update the specified user in storage.
     */
    public function updateUser(Request $request, int $id): RedirectResponse
    {
        if (Auth::user()->role !== 'owner') {
            return back()->withErrors(['auth' => 'Akses Ditolak: Hanya Owner yang dapat mengedit pengguna.']);
        }

        $user = User::findOrFail($id);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $id],
            'password' => ['nullable', 'string', 'min:6'],
            'role' => ['required', 'in:owner,staff'],
            'permissions' => ['nullable', 'array'],
        ]);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'permissions' => $request->permissions ?? [],
        ];

        if ($request->filled('password')) {
            $data['password'] = bcrypt($request->password);
        }

        $user->update($data);

        return redirect()->route('dashboard', ['activeTab' => 'users'])->with('success', 'User berhasil diperbarui!');
    }

    /**
     * Display listing of advance payments and outstanding settlements.
     */
    public function indexSettlements(): RedirectResponse
    {
        if (!Auth::user()->hasPermission('view_settlements')) {
            return redirect()->route('dashboard')->withErrors(['auth' => 'Akses Ditolak: Anda tidak memiliki izin untuk melihat halaman settlement.']);
        }
        return redirect()->route('dashboard', ['activeTab' => 'settlements']);
    }

    /**
     * Store a new advance payment (uang muka).
     */
    public function storeAdvance(Request $request): RedirectResponse
    {
        if (!Auth::user()->hasPermission('create_settlements')) {
            return back()->withErrors(['auth' => 'Akses Ditolak: Anda tidak memiliki izin untuk membuat uang muka (advance).']);
        }

        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_account_id' => ['required', 'exists:accounts,id'],
            'transaction_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:500'],
            'recipient_name' => ['required', 'string', 'max:100'],
        ]);

        $date = Carbon::parse($request->transaction_date);

        DB::transaction(function () use ($request, $date) {
            // Generate transaction number
            $prefix = 'TX-' . $date->format('Ymd') . '-';
            $lastCount = Transaction::where('transaction_number', 'like', $prefix . '%')->count();
            $txNumber = $prefix . str_pad($lastCount + 1, 4, '0', STR_PAD_LEFT);

            // 1. Create Transaction Header marked as Advance
            $tx = Transaction::create([
                'transaction_number' => $txNumber,
                'transaction_date' => $date,
                'description' => $request->description,
                'recipient_name' => $request->recipient_name,
                'is_advance' => true,
                'advance_status' => 'open',
                'created_by' => Auth::id(),
                'is_transferred' => $request->has('is_transferred'),
            ]);

            // 2. Double-Entry Bookkeeping:
            // Fetch Account 1202 - Uang Muka Pembelian (Asset)
            $advanceAccount = Account::where('code', '1202')->first();
            if (!$advanceAccount) {
                $advanceAccount = Account::create(['code' => '1202', 'name' => 'Uang Muka Pembelian', 'type' => 'asset']);
            }

            // Debit 1202 (Advance Asset account)
            JournalEntry::create([
                'transaction_id' => $tx->id,
                'account_id' => $advanceAccount->id,
                'type' => 'debit',
                'amount' => $request->amount,
            ]);

            // Credit chosen Cash/Bank source
            JournalEntry::create([
                'transaction_id' => $tx->id,
                'account_id' => $request->payment_account_id,
                'type' => 'credit',
                'amount' => $request->amount,
            ]);
        });

        return redirect()->route('dashboard', ['activeTab' => 'settlements'])->with('success', 'Transaksi Uang Muka (Advance) berhasil dicatat!');
    }

    /**
     * Settle an outstanding advance payment with a receipt.
     */
    public function settleAdvance(Request $request, int $id): RedirectResponse
    {
        if (!Auth::user()->hasPermission('process_settlements')) {
            return back()->withErrors(['auth' => 'Akses Ditolak: Anda tidak memiliki izin untuk memproses settlement.']);
        }

        $request->validate([
            'settlement_amount' => ['required', 'numeric', 'min:0.01'],
            'expense_account_id' => ['required', 'exists:accounts,id'],
            'receipt' => ['required', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:5120'], // Bon is mandatory
        ]);

        $tx = Transaction::findOrFail($id);
        if (!$tx->is_advance || $tx->advance_status !== 'open') {
            return back()->withErrors(['settlement' => 'Uang muka ini tidak dapat diselesaikan atau sudah selesai.']);
        }

        DB::transaction(function () use ($request, $tx) {
            // 1. Upload receipt attachment
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

            // 2. Fetch original advance amount from Jurnal
            $advanceAccount = Account::where('code', '1202')->first();
            $originalEntry = JournalEntry::where('transaction_id', $tx->id)
                ->where('account_id', $advanceAccount->id)
                ->first();
            $originalAmount = floatval($originalEntry->amount);

            // Fetch cash account used originally (for adjustment)
            $cashEntry = JournalEntry::where('transaction_id', $tx->id)
                ->where('account_id', '!=', $advanceAccount->id)
                ->first();
            $cashAccountId = $cashEntry->account_id;

            $settlementAmount = floatval($request->settlement_amount);

            // Update transaction status
            $tx->update([
                'advance_status' => 'settled',
                'settled_at' => now(),
                'settlement_amount' => $settlementAmount,
            ]);

            // 3. Double-entry adjustment entries for settlement:
            // a. Debit Expense account (Actual cost from receipt)
            JournalEntry::create([
                'transaction_id' => $tx->id,
                'account_id' => $request->expense_account_id,
                'type' => 'debit',
                'amount' => $settlementAmount,
            ]);

            // b. Credit 1202 - Uang Muka Pembelian (Clearing the asset)
            JournalEntry::create([
                'transaction_id' => $tx->id,
                'account_id' => $advanceAccount->id,
                'type' => 'credit',
                'amount' => $originalAmount,
            ]);

            // c. Adjust Cash account if there's difference
            $difference = $settlementAmount - $originalAmount;
            if ($difference > 0) {
                // Underpaid: Actual cost is higher than advance, company pays difference out of cash
                // Credit Cash account with difference
                JournalEntry::create([
                    'transaction_id' => $tx->id,
                    'account_id' => $cashAccountId,
                    'type' => 'credit',
                    'amount' => $difference,
                ]);
            } elseif ($difference < 0) {
                // Overpaid: Actual cost is lower than advance, employee returns difference to cash
                // Debit Cash account with difference (positive value)
                JournalEntry::create([
                    'transaction_id' => $tx->id,
                    'account_id' => $cashAccountId,
                    'type' => 'debit',
                    'amount' => abs($difference),
                ]);
            }
        });

        return redirect()->route('dashboard', ['activeTab' => 'settlements'])->with('success', 'Settlement Uang Muka berhasil diselesaikan dan dicatat!');
    }

    /**
     * Edit an existing settlement / advance payment.
     */
    public function editSettlement(Request $request, int $id): RedirectResponse
    {
        if (!Auth::user()->hasPermission('edit_settlements')) {
            return back()->withErrors(['auth' => 'Akses Ditolak: Anda tidak memiliki izin untuk mengubah data settlement.']);
        }

        $tx = Transaction::findOrFail($id);
        if (!$tx->is_advance) {
            return back()->withErrors(['settlement' => 'Transaksi ini bukan uang muka.']);
        }

        $isSettled = $tx->advance_status === 'settled';

        $rules = [
            'transaction_date' => ['required', 'date'],
            'recipient_name' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_account_id' => ['required', 'exists:accounts,id'],
            'description' => ['required', 'string', 'max:500'],
        ];

        if ($isSettled) {
            $rules['expense_account_id'] = ['required', 'exists:accounts,id'];
            $rules['settlement_amount'] = ['required', 'numeric', 'min:0.01'];
            $rules['receipt'] = ['nullable', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:5120'];
        }

        $request->validate($rules);

        $date = Carbon::parse($request->transaction_date);

        DB::transaction(function () use ($request, $tx, $date, $isSettled) {
            // 1. Update basic fields in Transaction
            $txData = [
                'transaction_date' => $date,
                'recipient_name' => $request->recipient_name,
                'description' => $request->description,
            ];

            if ($isSettled) {
                $txData['settlement_amount'] = floatval($request->settlement_amount);

                // Handle file upload replacement if present
                if ($request->hasFile('receipt')) {
                    // Delete old attachments
                    foreach ($tx->attachments as $oldAtt) {
                        if (Storage::disk('public')->exists($oldAtt->file_path)) {
                            Storage::disk('public')->delete($oldAtt->file_path);
                        }
                        $oldAtt->delete();
                    }

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
            }

            $tx->update($txData);

            // 2. Clear old JournalEntries to recreate them cleanly
            JournalEntry::where('transaction_id', $tx->id)->delete();

            // 3. Recreate JournalEntries
            $advanceAccount = Account::where('code', '1202')->first();
            if (!$advanceAccount) {
                $advanceAccount = Account::create(['code' => '1202', 'name' => 'Uang Muka Pembelian', 'type' => 'asset']);
            }

            // Debit 1202 (Advance Asset account)
            JournalEntry::create([
                'transaction_id' => $tx->id,
                'account_id' => $advanceAccount->id,
                'type' => 'debit',
                'amount' => floatval($request->amount),
            ]);

            // Credit chosen Cash/Bank source
            JournalEntry::create([
                'transaction_id' => $tx->id,
                'account_id' => $request->payment_account_id,
                'type' => 'credit',
                'amount' => floatval($request->amount),
            ]);

            // If settled, recreate settlement entries
            if ($isSettled) {
                $settlementAmount = floatval($request->settlement_amount);
                $originalAmount = floatval($request->amount);

                // a. Debit Expense account (Actual cost from receipt)
                JournalEntry::create([
                    'transaction_id' => $tx->id,
                    'account_id' => $request->expense_account_id,
                    'type' => 'debit',
                    'amount' => $settlementAmount,
                ]);

                // b. Credit 1202 - Uang Muka Pembelian (Clearing the asset)
                JournalEntry::create([
                    'transaction_id' => $tx->id,
                    'account_id' => $advanceAccount->id,
                    'type' => 'credit',
                    'amount' => $originalAmount,
                ]);

                // c. Adjust Cash account if there's difference
                $difference = $settlementAmount - $originalAmount;
                if ($difference > 0) {
                    // Underpaid: Credit Cash account with difference
                    JournalEntry::create([
                        'transaction_id' => $tx->id,
                        'account_id' => $request->payment_account_id,
                        'type' => 'credit',
                        'amount' => $difference,
                    ]);
                } elseif ($difference < 0) {
                    // Overpaid: Debit Cash account with difference
                    JournalEntry::create([
                        'transaction_id' => $tx->id,
                        'account_id' => $request->payment_account_id,
                        'type' => 'debit',
                        'amount' => abs($difference),
                    ]);
                }
            }
        });

        return redirect()->route('dashboard', ['activeTab' => 'settlements'])->with('success', 'Data Settlement berhasil diperbarui!');
    }

    /**
     * Remove the specified settlement from storage.
     */
    public function deleteSettlement(Request $request, int $id): RedirectResponse
    {
        if (!Auth::user()->hasPermission('delete_settlements')) {
            return back()->withErrors(['auth' => 'Akses Ditolak: Anda tidak memiliki izin untuk menghapus settlement.']);
        }

        try {
            $tx = Transaction::with('attachments')->findOrFail($id);
            if (!$tx->is_advance) {
                return back()->withErrors(['settlement' => 'Transaksi ini bukan uang muka.']);
            }

            DB::transaction(function () use ($tx) {
                foreach ($tx->attachments as $attachment) {
                    try {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($attachment->file_path);
                    } catch (\Throwable $e) {}
                }

                $tx->delete();
            });

            return redirect()->route('dashboard', ['activeTab' => 'settlements'])->with('success', 'Transaksi Uang Muka (Settlement) berhasil dihapus!');
        } catch (\Throwable $e) {
            return back()->withErrors(['delete_error' => 'Gagal menghapus settlement #' . $id . ': ' . $e->getMessage()]);
        }
    }

    /**
     * Remove multiple settlements from storage.
     */
    public function bulkDeleteSettlement(Request $request): RedirectResponse
    {
        if (!Auth::user()->hasPermission('delete_settlements')) {
            return back()->withErrors(['auth' => 'Akses Ditolak: Anda tidak memiliki izin untuk menghapus settlement.']);
        }

        $ids = $request->input('ids');
        if (empty($ids) || !is_array($ids)) {
            return back()->withErrors(['bulk' => 'Pilih setidaknya satu settlement untuk dihapus.']);
        }

        DB::transaction(function () use ($ids) {
            $transactions = Transaction::with('attachments')
                ->whereIn('id', $ids)
                ->where('is_advance', true)
                ->get();

            foreach ($transactions as $tx) {
                // Delete physical receipt files
                foreach ($tx->attachments as $attachment) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($attachment->file_path);
                }
                // Delete transaction (which automatically cascades to journal entries & attachments)
                $tx->delete();
            }
        });

        return redirect()->route('dashboard', ['activeTab' => 'settlements'])->with('success', 'Settlement terpilih berhasil dihapus!');
    }

    /**
     * Store a new Cash Advance loan.
     */
    public function storeLoan(Request $request): RedirectResponse
    {
        if (!Auth::user()->hasPermission('create_cash_advances')) {
            return back()->withErrors(['auth' => 'Akses Ditolak: Anda tidak memiliki izin untuk membuat Cash Advance.']);
        }

        $request->validate([
            'transaction_date' => 'required|date',
            'recipient_name' => 'required|string|max:255',
            'amount' => 'required|string',
            'payment_account_id' => 'required|exists:accounts,id',
            'description' => 'required|string|max:255',
        ]);

        $amount = floatval(str_replace('.', '', $request->amount));
        if ($amount <= 0) {
            return back()->withErrors(['amount' => 'Nominal pinjaman harus lebih besar dari 0.'])->withInput();
        }

        $loanAccount = Account::where('code', '1203')->first();
        if (!$loanAccount) {
            return back()->withErrors(['amount' => 'Akun Piutang Karyawan (1203) belum terdaftar di Chart of Accounts.'])->withInput();
        }

        DB::transaction(function () use ($request, $amount, $loanAccount) {
            $datePrefix = Carbon::parse($request->transaction_date)->format('Ymd');
            $countToday = Transaction::whereDate('transaction_date', $request->transaction_date)
                ->where('transaction_number', 'LIKE', "CA-{$datePrefix}-%")
                ->count();
            $seq = str_pad($countToday + 1, 4, '0', STR_PAD_LEFT);
            $txNum = "CA-{$datePrefix}-{$seq}";

            $isTransferred = $request->has('is_transferred');
            $transferredAmount = $isTransferred ? ($request->transferred_amount ? floatval(str_replace('.', '', $request->transferred_amount)) : $amount) : null;

            $tx = Transaction::create([
                'transaction_number' => $txNum,
                'transaction_date' => $request->transaction_date,
                'description' => $request->description,
                'recipient_name' => $request->recipient_name,
                'is_advance' => false,
                'is_loan' => true,
                'loan_status' => 'open',
                'loan_repaid_amount' => 0,
                'created_by' => Auth::id(),
                'is_transferred' => $isTransferred,
                'amount' => $amount,
                'transferred_amount' => $transferredAmount,
            ]);

            if ($isTransferred) {
                // Debit 1203
                JournalEntry::create([
                    'transaction_id' => $tx->id,
                    'account_id' => $loanAccount->id,
                    'type' => 'debit',
                    'amount' => $transferredAmount,
                ]);

                // Credit Cash/Bank source
                JournalEntry::create([
                    'transaction_id' => $tx->id,
                    'account_id' => $request->payment_account_id,
                    'type' => 'credit',
                    'amount' => $transferredAmount,
                ]);
            }
        });

        return redirect()->route('dashboard', ['activeTab' => 'cash-advances'])->with('success', 'Pinjaman Cash Advance berhasil dicatat!');
    }

    /**
     * Edit an existing Cash Advance loan.
     */
    public function editLoan(Request $request, $id): RedirectResponse
    {
        if (!Auth::user()->hasPermission('edit_cash_advances')) {
            return back()->withErrors(['auth' => 'Akses Ditolak: Anda tidak memiliki izin untuk mengubah Cash Advance.']);
        }

        $tx = Transaction::where('is_loan', true)->findOrFail($id);

        $request->validate([
            'transaction_date' => 'required|date',
            'recipient_name' => 'required|string|max:255',
            'amount' => 'required|string',
            'payment_account_id' => 'required|exists:accounts,id',
            'description' => 'required|string|max:255',
            'is_transferred' => 'nullable',
            'transferred_amount' => 'nullable|string',
        ]);

        $amount = floatval(str_replace('.', '', $request->amount));
        if ($amount <= 0) {
            return back()->withErrors(['amount' => 'Nominal pinjaman harus lebih besar dari 0.']);
        }

        // Validate transferred amount if is_transferred is set
        $isTransferred = $request->has('is_transferred');
        $transferredAmount = null;
        if ($isTransferred) {
            $transferredAmount = $request->transferred_amount ? floatval(str_replace('.', '', $request->transferred_amount)) : $amount;
            if ($transferredAmount <= 0) {
                return back()->withErrors(['transferred_amount' => 'Nominal yang ditransfer harus lebih besar dari 0.']);
            }
        }

        $baseRepayAmount = $isTransferred ? $transferredAmount : $amount;
        if ($baseRepayAmount < floatval($tx->loan_repaid_amount)) {
            return back()->withErrors(['amount' => 'Nominal pinjaman/transfer tidak boleh kurang dari total angsuran yang sudah dibayarkan (Rp ' . number_format($tx->loan_repaid_amount, 0, ',', '.') . ').']);
        }

        $loanAccount = Account::where('code', '1203')->first();
        if (!$loanAccount) {
            return back()->withErrors(['amount' => 'Akun Piutang Karyawan (1203) belum terdaftar di Chart of Accounts.']);
        }

        DB::transaction(function () use ($tx, $request, $amount, $isTransferred, $transferredAmount, $loanAccount) {
            $tx->update([
                'transaction_date' => $request->transaction_date,
                'description' => $request->description,
                'recipient_name' => $request->recipient_name,
                'is_transferred' => $isTransferred,
                'amount' => $amount,
                'transferred_amount' => $transferredAmount,
            ]);

            $baseAmount = $isTransferred ? $transferredAmount : $amount;
            $repaid = floatval($tx->loan_repaid_amount);
            if ($repaid >= $baseAmount) {
                $tx->update(['loan_status' => 'repaid']);
            } else {
                $tx->update(['loan_status' => 'open']);
            }

            $tx->journalEntries()->delete();

            if ($isTransferred) {
                // Debit 1203
                JournalEntry::create([
                    'transaction_id' => $tx->id,
                    'account_id' => $loanAccount->id,
                    'type' => 'debit',
                    'amount' => $transferredAmount,
                ]);

                // Credit Cash/Bank source
                JournalEntry::create([
                    'transaction_id' => $tx->id,
                    'account_id' => $request->payment_account_id,
                    'type' => 'credit',
                    'amount' => $transferredAmount,
                ]);
            }
        });

        return redirect()->route('dashboard', ['activeTab' => 'cash-advances'])->with('success', 'Pinjaman Cash Advance berhasil diperbarui!');
    }

    /**
     * Delete a Cash Advance loan.
     */
    public function deleteLoan($id): RedirectResponse
    {
        if (!Auth::user()->hasPermission('delete_cash_advances')) {
            return back()->withErrors(['auth' => 'Akses Ditolak: Anda tidak memiliki izin untuk menghapus Cash Advance.']);
        }

        $tx = Transaction::where('is_loan', true)->findOrFail($id);

        DB::transaction(function () use ($tx) {
            $repayments = Transaction::where('loan_parent_id', $tx->id)->get();
            foreach ($repayments as $rep) {
                $rep->delete();
            }
            $tx->delete();
        });

        return redirect()->route('dashboard', ['activeTab' => 'cash-advances'])->with('success', 'Pinjaman Cash Advance beserta riwayat angsurannya berhasil dihapus!');
    }

    /**
     * Store a loan repayment (angsuran).
     */
    public function storeRepayment(Request $request, $id): RedirectResponse
    {
        if (!Auth::user()->hasPermission('create_cash_advances')) {
            return back()->withErrors(['auth' => 'Akses Ditolak: Anda tidak memiliki izin untuk mencatat angsuran.']);
        }

        $loan = Transaction::where('is_loan', true)->findOrFail($id);
        if ($loan->loan_status === 'repaid') {
            return back()->withErrors(['repay' => 'Pinjaman ini sudah lunas.']);
        }

        $request->validate([
            'transaction_date' => 'required|date',
            'amount' => 'required|string',
            'payment_account_id' => 'required|exists:accounts,id',
            'description' => 'required|string|max:255',
        ]);

        $amount = floatval(str_replace('.', '', $request->amount));
        if ($amount <= 0) {
            return back()->withErrors(['amount' => 'Nominal angsuran harus lebih besar dari 0.']);
        }

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
            return back()->withErrors(['amount' => 'Nominal angsuran tidak boleh melebihi sisa pinjaman (Rp ' . number_format($remaining, 0, ',', '.') . ').']);
        }

        $loanAccount = Account::where('code', '1203')->first();
        if (!$loanAccount) {
            return back()->withErrors(['amount' => 'Akun Piutang Karyawan (1203) belum terdaftar.']);
        }

        DB::transaction(function () use ($loan, $request, $amount, $loanAccount, $currentRepaid, $loanAmount) {
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

            // Debit chosen Cash/Bank destination
            JournalEntry::create([
                'transaction_id' => $repTx->id,
                'account_id' => $request->payment_account_id,
                'type' => 'debit',
                'amount' => $amount,
            ]);

            // Credit 1203
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
        });

        return redirect()->route('dashboard', ['activeTab' => 'cash-advances'])->with('success', 'Pembayaran angsuran berhasil dicatat!');
    }

    /**
     * Delete a single loan repayment (angsuran).
     */
    public function deleteRepayment($repayment_id): RedirectResponse
    {
        if (!Auth::user()->hasPermission('delete_cash_advances')) {
            return back()->withErrors(['auth' => 'Akses Ditolak: Anda tidak memiliki izin untuk menghapus angsuran.']);
        }

        $rep = Transaction::whereNotNull('loan_parent_id')->findOrFail($repayment_id);
        $loan = Transaction::findOrFail($rep->loan_parent_id);

        DB::transaction(function () use ($rep, $loan) {
            $repAmount = 0;
            foreach ($rep->journalEntries as $entry) {
                if (Str::startsWith($entry->account->code, '11') && $entry->type === 'debit') {
                    $repAmount = floatval($entry->amount);
                }
            }
            if ($repAmount === 0 && $rep->journalEntries->isNotEmpty()) {
                $repAmount = floatval($rep->journalEntries->first()->amount);
            }

            $rep->delete();

            $newRepaid = max(0, floatval($loan->loan_repaid_amount) - $repAmount);
            
            $loanAmount = 0;
            foreach ($loan->journalEntries as $entry) {
                if (Str::startsWith($entry->account->code, '1203') && $entry->type === 'debit') {
                    $loanAmount = floatval($entry->amount);
                }
            }
            if ($loanAmount === 0 && $loan->journalEntries->isNotEmpty()) {
                $loanAmount = floatval($loan->journalEntries->first()->amount);
            }

            $loan->update([
                'loan_repaid_amount' => $newRepaid,
                'loan_status' => ($newRepaid >= $loanAmount) ? 'repaid' : 'open',
            ]);
        });

        return redirect()->route('dashboard', ['activeTab' => 'cash-advances'])->with('success', 'Catatan angsuran berhasil dihapus dan saldo pinjaman dipulihkan!');
    }

    /**
     * Mark a pending reimbursement as transferred & post cash reduction journal entry.
     */
    public function transferReimbursement(Request $request, int $id): RedirectResponse
    {
        if (!Auth::user()->hasPermission('edit_transactions')) {
            return back()->withErrors(['auth' => 'Akses Ditolak: Anda tidak memiliki izin untuk mengedit transaksi.']);
        }

        $request->validate([
            'payment_account_id' => ['required', 'exists:accounts,id'],
            'transfer_date' => ['required', 'date'],
            'transfer_proof' => ['required', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:5120'], // Max 5MB
        ]);

        $tx = Transaction::findOrFail($id);
        if (!$tx->is_reimbursement || $tx->reimbursement_status !== 'pending') {
            return back()->withErrors(['error' => 'Transaksi bukan reimbursement pending.']);
        }

        $date = Carbon::parse($request->transfer_date);

        DB::transaction(function () use ($request, $tx, $date) {
            // Upload transfer proof file
            $file = $request->file('transfer_proof');
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('receipts', $filename, 'public');

            // Update transaction status
            $tx->update([
                'reimbursement_status' => 'transferred',
                'transfer_proof_path' => $path,
            ]);

            // Recreate journal entries to Debit expense account, Credit Kas/Bank
            $expenseEntry = JournalEntry::where('transaction_id', $tx->id)
                ->where('type', 'debit')
                ->first();

            $amount = $expenseEntry ? floatval($expenseEntry->amount) : 0;
            $expenseAccountId = $expenseEntry ? $expenseEntry->account_id : Account::where('code', 'like', '5%')->first()->id;

            // Clear old entries and recreate
            JournalEntry::where('transaction_id', $tx->id)->delete();

            JournalEntry::create([
                'transaction_id' => $tx->id,
                'account_id' => $expenseAccountId,
                'type' => 'debit',
                'amount' => $amount,
            ]);

            JournalEntry::create([
                'transaction_id' => $tx->id,
                'account_id' => $request->payment_account_id,
                'type' => 'credit',
                'amount' => $amount,
            ]);
        });

        return back()->with('success', 'Pembayaran reimbursement berhasil ditransfer!');
    }

    /**
     * Store new employee.
     */
    public function storeEmployee(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'hti_id' => 'nullable|string',
            'first_name' => 'required|string',
            'last_name' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'place_of_birth' => 'nullable|string',
            'sex' => 'nullable|string',
            'religion' => 'nullable|string',
            'marital_status' => 'nullable|string',
            'nationality' => 'nullable|string',
            'permanent_address' => 'nullable|string',
            'permanent_city' => 'nullable|string',
            'correspondence_address' => 'nullable|string',
            'correspondence_city' => 'nullable|string',
            'telp_no' => 'nullable|string',
            'handphone' => 'nullable|string',
            'email' => 'nullable|email',
            'ktp_no' => 'nullable|string',
            'passport_no' => 'nullable|string',
            'npwp_no' => 'nullable|string',
            'jamsostek_no' => 'nullable|string',
            'tax_status' => 'nullable|string',
            'division' => 'nullable|string',
            'employee_status' => 'nullable|string',
            'rehired_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'resign_date' => 'nullable|date',
            'temp_ext' => 'nullable|string',
            'status' => 'nullable|string',
            'is_freelance' => 'nullable|boolean',
        ]);

        // Auto-generate employee_no starting from BS0001
        DB::transaction(function() use (&$data) {
            $lastEmployee = Employee::where('employee_no', 'like', 'BS%')
                ->orderBy('employee_no', 'desc')
                ->first();

            $nextNumber = 1;
            if ($lastEmployee) {
                $lastNum = intval(substr($lastEmployee->employee_no, 2));
                $nextNumber = $lastNum + 1;
            }

            $data['employee_no'] = sprintf('BS%04d', $nextNumber);
            $data['fullname'] = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
            $data['is_freelance'] = isset($data['is_freelance']) ? (bool)$data['is_freelance'] : false;

            Employee::create($data);
        });

        return redirect()->route('dashboard', ['activeTab' => 'employee'])->with('success', 'Karyawan baru berhasil ditambahkan!');
    }

    /**
     * Update existing employee.
     */
    public function updateEmployee(Request $request, $id): RedirectResponse
    {
        $employee = Employee::findOrFail($id);

        $data = $request->validate([
            'hti_id' => 'nullable|string',
            'first_name' => 'required|string',
            'last_name' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'place_of_birth' => 'nullable|string',
            'sex' => 'nullable|string',
            'religion' => 'nullable|string',
            'marital_status' => 'nullable|string',
            'nationality' => 'nullable|string',
            'permanent_address' => 'nullable|string',
            'permanent_city' => 'nullable|string',
            'correspondence_address' => 'nullable|string',
            'correspondence_city' => 'nullable|string',
            'telp_no' => 'nullable|string',
            'handphone' => 'nullable|string',
            'email' => 'nullable|email',
            'ktp_no' => 'nullable|string',
            'passport_no' => 'nullable|string',
            'npwp_no' => 'nullable|string',
            'jamsostek_no' => 'nullable|string',
            'tax_status' => 'nullable|string',
            'division' => 'nullable|string',
            'employee_status' => 'nullable|string',
            'rehired_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'resign_date' => 'nullable|date',
            'temp_ext' => 'nullable|string',
            'status' => 'nullable|string',
            'is_freelance' => 'nullable|boolean',
        ]);

        $data['fullname'] = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
        $data['is_freelance'] = isset($data['is_freelance']) ? (bool)$data['is_freelance'] : false;

        $employee->update($data);

        return redirect()->route('dashboard', ['activeTab' => 'employee'])->with('success', 'Data karyawan berhasil diperbarui!');
    }

    /**
     * Delete employee.
     */
    public function deleteEmployee($id): RedirectResponse
    {
        $employee = Employee::findOrFail($id);
        $employee->delete();

        return redirect()->route('dashboard', ['activeTab' => 'employee'])->with('success', 'Karyawan berhasil dihapus!');
    }
}
