<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'type'];

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }
}
