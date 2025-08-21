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
        'is_main_product',
        'cost_weight',
    ];

    protected $casts = [
        'price' => 'float',
        'cost' => 'float',
        'profit_margin' => 'float',
        'stock' => 'integer',
        'is_main_product' => 'boolean',
        'cost_weight' => 'float',
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
