<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class ContractItem extends Model
{
    use HasTranslations;

    public $translatable = ['specification', 'counterparty'];

    protected $fillable = [
        'contract_id',
        'sort',
        'specification',
        'amount',
        'counterparty',
        'agreement_ref',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'sort' => 'integer',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
