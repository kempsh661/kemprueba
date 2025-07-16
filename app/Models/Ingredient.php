<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'code',
        'description',
        'unit',
        'quantity_purchased',
        'purchase_value',
        'portion_quantity',
        'portion_unit',
        'portion_cost',
        'price',
        'stock',
        'min_stock',
        'max_stock',
        'supplier',
        'location',
    ];

    protected $casts = [
        'quantity_purchased' => 'float',
        'purchase_value' => 'float',
        'portion_quantity' => 'float',
        'portion_cost' => 'float',
        'price' => 'float',
        'stock' => 'integer',
        'min_stock' => 'integer',
        'max_stock' => 'integer',
    ];

    /**
     * Relación con el usuario
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Accesores para compatibilidad con el frontend
     */
    public function getQuantityPurchasedAttribute()
    {
        return $this->attributes['quantity_purchased'];
    }

    public function getPurchaseValueAttribute()
    {
        return $this->attributes['purchase_value'];
    }

    public function getPortionQuantityAttribute()
    {
        return $this->attributes['portion_quantity'];
    }

    public function getPortionCostAttribute()
    {
        return $this->attributes['portion_cost'];
    }

    public function getMinStockAttribute()
    {
        return $this->attributes['min_stock'];
    }

    public function getMaxStockAttribute()
    {
        return $this->attributes['max_stock'];
    }

    public function getPortionUnitAttribute()
    {
        return $this->attributes['portion_unit'];
    }

    public function getCreatedAtAttribute()
    {
        return $this->attributes['created_at'];
    }

    public function getUpdatedAtAttribute()
    {
        return $this->attributes['updated_at'];
    }
}
