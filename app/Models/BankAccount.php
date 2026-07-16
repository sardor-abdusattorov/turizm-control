<?php

namespace App\Models;

use Database\Factories\BankAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One bank account of a counterparty. A counterparty typically holds one
 * account per currency (UZS / USD / EUR / RUB) at the same bank, so the
 * generated contract document can pull the account matching the deal's
 * currency. A currency_id of null marks a currency-agnostic account used as
 * the fallback when no per-currency match exists.
 */
class BankAccount extends Model
{
    /** @use HasFactory<BankAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'currency_id',
        'account_number',
        'bank_name',
        'mfo',
        'swift',
        'sort',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
