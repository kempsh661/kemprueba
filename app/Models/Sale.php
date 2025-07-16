<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    public $timestamps = true;
    
    protected $fillable = [
        'user_id',
        'customer_id',
        'customer_name',
        'customer_document',
        'customer_phone',
        'customer_email',
        'total',
        'subtotal',
        'tax',
        'discount',
        'payment_method',
        'cash_received',
        'change',
        'transaction_number',
        'payments',
        'sale_date',
        'items',
        'remaining_balance',
        'details',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'cash_received' => 'decimal:2',
        'change' => 'decimal:2',
        'remaining_balance' => 'decimal:2',
        'payments' => 'array',
        'items' => 'array',
        'details' => 'array',
        'sale_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creditPayments(): HasMany
    {
        return $this->hasMany(CreditPayment::class);
    }
}
