<?php

namespace App\Models;

use Database\Factories\BankAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankAccount extends Model
{
    /** @use HasFactory<BankAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'currency_id',
        'account_number',
        'bank_name',
        'bank_address',
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
