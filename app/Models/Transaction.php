<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_number', 
        'transaction_date', 
        'description', 
        'recipient_name',
        'is_advance', 
        'advance_status', 
        'settled_at', 
        'settlement_amount', 
        'is_loan',
        'loan_status',
        'loan_repaid_amount',
        'loan_parent_id',
        'is_reimbursement',
        'reimbursement_status',
        'transfer_proof_path',
        'created_by'
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'is_advance' => 'boolean',
        'settled_at' => 'datetime',
        'is_loan' => 'boolean',
        'is_reimbursement' => 'boolean',
    ];

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parentLoan(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'loan_parent_id');
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(Transaction::class, 'loan_parent_id');
    }
}
