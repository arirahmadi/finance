<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Attachment;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        $formattedTransactions = $transactions->map(function ($tx) use (&$totalIn, &$totalOut) {
            $type = 'unknown';
            $amount = 0;
            $category = null;
            $categoryId = null;
            $paymentSource = null;
            $paymentAccountId = null;

            foreach ($tx->journalEntries as $entry) {
                $accCode = $entry->account->code;
                $isAsset = Str::startsWith($accCode, '11');

                if ($isAsset) {
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

            if ($type === 'in') {
                $totalIn += $amount;
            } elseif ($type === 'out') {
                $totalOut += $amount;
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
                'attachments' => $tx->attachments,
                'creator' => $tx->creator->name ?? null,
            ];
        });

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

            foreach ($tx->journalEntries as $entry) {
                if (Str::startsWith($entry->account->code, '11') && $entry->type === 'credit') {
                    $paymentSource = $entry->account->name;
                    $paymentAccountId = $entry->account_id;
                    $amount = floatval($entry->amount);
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
            ];
        });

        $settlementSummary = (object) [
            'total_outstanding' => $totalOutstanding,
            'total_settled' => $totalSettled,
        ];

        return view('dashboard', [
            'transactions' => $formattedTransactions,
            'summary' => (object) [
                'total_in' => $totalIn,
                'total_out' => $totalOut,
                'net_flow' => $totalIn - $totalOut,
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
            'activeTab' => $request->input('activeTab', 'dashboard')
        ]);
    }

    /**
     * Store new transaction via Web form.
     */
    public function storeTransaction(Request $request): RedirectResponse
    {
        $request->validate([
            'type' => ['required', 'in:in,out'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'account_id' => ['required', 'exists:accounts,id'],
            'payment_account_id' => ['required', 'exists:accounts,id'],
            'transaction_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
            'receipt' => ['nullable', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:5120'], // Max 5MB
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

            // Create Transaction
            $tx = Transaction::create([
                'transaction_number' => $transactionNumber,
                'transaction_date' => $date,
                'description' => $request->description,
                'created_by' => $userId,
            ]);

            // Post Jurnal Double Entry
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
            'payment_account_id' => ['required', 'exists:accounts,id'],
            'transaction_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
            'receipt' => ['nullable', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:5120'], // Max 5MB
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

        $tx = Transaction::with('attachments')->findOrFail($id);

        DB::transaction(function () use ($tx) {
            // Delete physical receipt files first
            foreach ($tx->attachments as $attachment) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($attachment->file_path);
            }

            // Delete transaction (which automatically cascades to journal entries & attachments)
            $tx->delete();
        });

        return redirect()->route('dashboard', ['activeTab' => $request->input('activeTab', 'transactions')])->with('success', 'Transaksi berhasil dihapus!');
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
        if (!Auth::user()->hasPermission('edit_settlements')) {
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
     * Remove the specified settlement from storage.
     */
    public function deleteSettlement(Request $request, int $id): RedirectResponse
    {
        if (!Auth::user()->hasPermission('delete_settlements')) {
            return back()->withErrors(['auth' => 'Akses Ditolak: Anda tidak memiliki izin untuk menghapus settlement.']);
        }

        $tx = Transaction::with('attachments')->findOrFail($id);
        if (!$tx->is_advance) {
            return back()->withErrors(['settlement' => 'Transaksi ini bukan uang muka.']);
        }

        DB::transaction(function () use ($tx) {
            // Delete physical receipt files first
            foreach ($tx->attachments as $attachment) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($attachment->file_path);
            }

            // Delete transaction (which automatically cascades to journal entries & attachments)
            $tx->delete();
        });

        return redirect()->route('dashboard', ['activeTab' => 'settlements'])->with('success', 'Transaksi Uang Muka (Settlement) berhasil dihapus!');
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
}
