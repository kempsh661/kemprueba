<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedCostPeriod extends Model
{
    protected $fillable = [
        'user_id',
        'fixed_cost_id',
        'month',
        'is_active',
        'is_paid',
        'partial_amount',
        'paid_amount',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_paid' => 'boolean',
        'partial_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    public function fixedCost(): BelongsTo
    {
        return $this->belongsTo(FixedCost::class);
    }
}











