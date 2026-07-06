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
        'created_by'
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'is_advance' => 'boolean',
        'settled_at' => 'datetime',
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
}
