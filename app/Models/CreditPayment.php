<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditPayment extends Model
{
    protected $fillable = [
        'sale_id',
        'amount',
        'payment_method',
        'transaction_number'
    ];

    protected $casts = [
        'amount' => 'decimal:2'
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
