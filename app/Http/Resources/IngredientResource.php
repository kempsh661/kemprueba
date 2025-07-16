<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IngredientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'unit' => $this->unit,
            'quantityPurchased' => $this->quantity_purchased,
            'purchaseValue' => $this->purchase_value,
            'portionQuantity' => $this->portion_quantity,
            'portionCost' => $this->portion_cost,
            'portionUnit' => $this->portion_unit,
            'stock' => $this->stock,
            'minStock' => $this->min_stock,
            'maxStock' => $this->max_stock,
            'code' => $this->code,
            'isActive' => true, // Por defecto activo
            'supplier' => $this->supplier,
            'location' => $this->location,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'price' => $this->price,
        ];
    }
}
