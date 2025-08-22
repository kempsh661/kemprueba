<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Purchase extends Model
{
    public $timestamps = true;
    
    protected $fillable = [
        'user_id',
        'amount',
        'date',
        'category',
        'concept',
        'notes',
        'fixed_cost_id',
        'is_partial_payment',
    ];

    protected $casts = [
        'date' => 'datetime',
        'amount' => 'decimal:2',
        'is_partial_payment' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fixedCost(): BelongsTo
    {
        return $this->belongsTo(FixedCost::class);
    }
}
