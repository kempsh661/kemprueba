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
        'status',
        'reversal_reason',
        'reversed_at',
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
        'sale_date' => 'datetime',
        'reversed_at' => 'datetime',
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

    // Accessor para items - asegura que siempre devuelva un array
    public function getItemsAttribute($value)
    {
        if (is_string($value)) {
            return json_decode($value, true) ?: [];
        }
        return is_array($value) ? $value : [];
    }

    // Mutator para items - asegura que siempre se guarde como JSON
    public function setItemsAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes['items'] = json_encode($value);
        } else {
            $this->attributes['items'] = $value;
        }
    }
}
