<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Calcular el costo total basado en los ingredientes
        $totalCost = 0;
        $costs = [];
        
        if ($this->costs) {
            foreach ($this->costs as $cost) {
                $ingredient = $cost->ingredient;
                $costValue = $ingredient->portion_cost * $cost->quantity;
                $totalCost += $costValue;
                
                $costs[] = [
                    'id' => $cost->id,
                    'ingredientId' => $cost->ingredient_id,
                    'quantity' => $cost->quantity,
                    'ingredient' => [
                        'id' => $ingredient->id,
                        'name' => $ingredient->name,
                        'unit' => $ingredient->unit,
                        'price' => $ingredient->price,
                        'portionQuantity' => $ingredient->portion_quantity,
                        'portionUnit' => $ingredient->portion_unit,
                        'portionCost' => $ingredient->portion_cost,
                    ]
                ];
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'categoryId' => $this->category_id,
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'code' => $this->category->code,
                ];
            }),
            'costs' => $costs,
            'cost' => $totalCost, // Usar el costo calculado en lugar del almacenado
            'price' => $this->price,
            'profitMargin' => $this->profit_margin,
            'stock' => $this->stock,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
} 