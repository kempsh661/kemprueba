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
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_paid' => 'boolean',
    ];

    public function fixedCost(): BelongsTo
    {
        return $this->belongsTo(FixedCost::class);
    }
}








