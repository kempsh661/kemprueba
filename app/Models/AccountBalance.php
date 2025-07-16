<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountBalance extends Model
{
    public $timestamps = true;
    
    protected $fillable = [
        'user_id',
        'bank_balance',
        'nequi_aleja',
        'nequi_kem',
        'cash_balance',
        'total_balance',
        'notes',
        'date',
        'is_closed',
        'type',
    ];
}
