<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCost;
use App\Http\Resources\ProductResource;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $products = Product::with(['category', 'costs.ingredient'])
            ->where('user_id', $userId)
            ->get();
            
        return response()->json([
            'success' => true,
            'data' => ProductResource::collection($products)
        ]);
    }

    public function store(Request $request)
    {
        $userId = $request->user()->id;
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'purchase_price' => 'required|numeric|min:0',
            // ... otros campos si es necesario ...
        ]);
        
        $data = $request->only([
            'name', 'code', 'description', 'categoryId', 
            'price', 'cost', 'profitMargin', 'stock', 'purchase_price'
        ]);
        
        $data['user_id'] = $userId;
        $data['category_id'] = $data['categoryId'] ?? null;
        $data['profit_margin'] = $data['profitMargin'] ?? 30;
        
        // Crear el producto
        $product = Product::create($data);
        
        // Crear los costos de ingredientes si se proporcionan
        $totalCost = 0;
        if ($request->has('costs') && is_array($request->costs)) {
            foreach ($request->costs as $cost) {
                ProductCost::create([
                    'product_id' => $product->id,
                    'ingredient_id' => $cost['ingredientId'],
                    'quantity' => $cost['quantity']
                ]);
                
                // Calcular el costo total
                $ingredient = \App\Models\Ingredient::find($cost['ingredientId']);
                if ($ingredient) {
                    $totalCost += $ingredient->portion_cost * $cost['quantity'];
                }
            }
        }
        
        // Actualizar el costo del producto
        $product->update(['cost' => $totalCost]);
        
        // Cargar las relaciones para la respuesta
        $product->load(['category', 'costs.ingredient']);
        
        return response()->json([
            'success' => true,
            'data' => new ProductResource($product)
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $userId = $request->user()->id;
        $product = Product::with(['category', 'costs.ingredient'])
            ->where('user_id', $userId)
            ->findOrFail($id);
            
        return response()->json([
            'success' => true,
            'data' => new ProductResource($product)
        ]);
    }

    public function update(Request $request, $id)
    {
        $userId = $request->user()->id;
        $product = Product::where('user_id', $userId)->findOrFail($id);
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'purchase_price' => 'required|numeric|min:0',
            // ... otros campos si es necesario ...
        ]);
        
        $data = $request->only([
            'name', 'code', 'description', 'categoryId', 
            'price', 'cost', 'profitMargin', 'stock', 'purchase_price'
        ]);
        
        $data['category_id'] = $data['categoryId'] ?? null;
        $data['profit_margin'] = $data['profitMargin'] ?? 30;
        
        $product->update($data);
        
        // Actualizar costos si se proporcionan
        $totalCost = 0;
        if ($request->has('costs') && is_array($request->costs)) {
            // Eliminar costos existentes
            $product->costs()->delete();
            
            // Crear nuevos costos
            foreach ($request->costs as $cost) {
                ProductCost::create([
                    'product_id' => $product->id,
                    'ingredient_id' => $cost['ingredientId'],
                    'quantity' => $cost['quantity']
                ]);
                
                // Calcular el costo total
                $ingredient = \App\Models\Ingredient::find($cost['ingredientId']);
                if ($ingredient) {
                    $totalCost += $ingredient->portion_cost * $cost['quantity'];
                }
            }
            
            // Actualizar el costo del producto
            $product->update(['cost' => $totalCost]);
        }
        
        $product->load(['category', 'costs.ingredient']);
        
        return response()->json([
            'success' => true,
            'data' => new ProductResource($product)
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $userId = $request->user()->id;
        $product = Product::where('user_id', $userId)->findOrFail($id);
        $product->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Producto eliminado exitosamente'
        ]);
    }

    public function calculateFixedCostsManual(Request $request)
    {
        $userId = $request->user()->id;
        $data = $request->validate([
            'products' => 'required|array',
            'products.*.id' => 'required|integer|exists:products,id',
            'products.*.name' => 'required|string',
            'products.*.quantity' => 'required|numeric|min:0',
            'fixedCosts' => 'required|array',
            'fixedCosts.*.name' => 'required|string',
            'fixedCosts.*.amount' => 'required|numeric|min:0',
        ]);

        $totalFixedCosts = collect($data['fixedCosts'])->sum('amount');
        $totalProductQuantity = collect($data['products'])->sum('quantity');

        $results = collect($data['products'])->map(function($product) use ($totalFixedCosts, $totalProductQuantity) {
            $fixedCostPerUnit = $totalProductQuantity > 0 ? ($totalFixedCosts * ($product['quantity'] / $totalProductQuantity)) : 0;
            return [
                'id' => $product['id'],
                'name' => $product['name'],
                'quantity' => $product['quantity'],
                'fixedCost' => round($fixedCostPerUnit, 2)
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $results
        ]);
    }
}
