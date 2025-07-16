<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'type',
        'quantity',
        'description',
    ];
}
