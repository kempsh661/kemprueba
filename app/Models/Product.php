<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'code',
        'description',
        'category_id',
        'price',
        'cost',
        'profit_margin',
        'purchase_price',
        'stock',
    ];

    protected $casts = [
        'price' => 'float',
        'cost' => 'float',
        'profit_margin' => 'float',
        'stock' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function costs()
    {
        return $this->hasMany(ProductCost::class);
    }
}
