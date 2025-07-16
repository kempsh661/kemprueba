<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedCost extends Model
{
    public $timestamps = true;
    
    protected $fillable = [
        'user_id',
        'name',
        'amount',
        'description',
        'frequency',
        'due_date',
        'category',
        'is_active',
        'is_paid',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
        'is_paid' => 'boolean',
        'due_date' => 'integer',
    ];

    protected $appends = ['dueDate', 'isActive', 'isPaid'];

    public function getDueDateAttribute($value)
    {
        return $this->attributes['due_date'];
    }

    public function getIsActiveAttribute($value)
    {
        return $this->attributes['is_active'];
    }

    public function getIsPaidAttribute($value)
    {
        return $this->attributes['is_paid'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
