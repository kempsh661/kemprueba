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

    // Fix: convertir string vacío a null para purchase_price
    if ($request->has('purchase_price') && $request->input('purchase_price') === '') {
        $request->merge(['purchase_price' => null]);
    }

    $request->validate([
        'name' => 'sometimes|required|string|max:255',
        'price' => 'sometimes|required|numeric|min:0',
        'purchase_price' => 'nullable|numeric|min:0',
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

    public function calculateFixedCosts(Request $request)
    {
        $userId = $request->user()->id;
        $data = $request->validate([
            'productId' => 'required|integer|exists:products,id',
            'period' => 'required|string|in:lastMonth,lastQuarter,lastYear,custom',
            'workingDays' => 'required|integer|min:1|max:31',
            'weeks' => 'required|integer|min:1|max:52',
            'profitMargin' => 'required|numeric|min:0|max:100',
        ]);

        try {
            // Obtener el producto
            $product = Product::where('user_id', $userId)->findOrFail($data['productId']);
            
            // Obtener costos fijos del período
            $fixedCosts = \App\Models\FixedCost::where('user_id', $userId)
                ->where('is_paid', true)
                ->get();
            
            $totalFixedCosts = $fixedCosts->sum('amount');
            
            // Calcular costos por período
            $periodMultiplier = $this->getPeriodMultiplier($data['period'], $data['workingDays'], $data['weeks']);
            $totalFixedCostsForPeriod = $totalFixedCosts * $periodMultiplier;
            
            // Obtener estadísticas de ventas del producto (simulado por ahora)
            $estimatedSales = $this->estimateProductSales($product, $data['period']);
            
            if ($estimatedSales <= 0) {
                // Si no hay ventas estimadas, solicitar input manual
                return response()->json([
                    'success' => true,
                    'data' => [
                        'needsManualInput' => true,
                        'message' => 'No hay suficientes datos de ventas para calcular automáticamente. Por favor, ingrese manualmente las unidades vendidas.',
                        'product' => [
                            'id' => $product->id,
                            'name' => $product->name,
                            'estimatedSales' => 0
                        ]
                    ]
                ]);
            }
            
            // Calcular costo fijo por unidad usando sistema ponderado
            $fixedCostPerUnit = $this->calculateWeightedFixedCost($product, $totalFixedCostsForPeriod, $data['period']);
            
            // Calcular costo total por unidad
            $totalCostPerUnit = ($product->cost ?? 0) + $fixedCostPerUnit;
            
            // Calcular precio de venta recomendado
            $recommendedPrice = $totalCostPerUnit / (1 - ($data['profitMargin'] / 100));
            
            $results = [
                'needsManualInput' => false,
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'cost' => $product->cost ?? 0,
                    'currentPrice' => $product->price ?? 0,
                    'estimatedSales' => $estimatedSales
                ],
                'fixedCosts' => [
                    'total' => $totalFixedCosts,
                    'periodMultiplier' => $periodMultiplier,
                    'totalForPeriod' => $totalFixedCostsForPeriod,
                    'perUnit' => round($fixedCostPerUnit, 2)
                ],
                'costAnalysis' => [
                    'variableCost' => $product->cost ?? 0,
                    'fixedCost' => round($fixedCostPerUnit, 2),
                    'totalCost' => round($totalCostPerUnit, 2),
                    'profitMargin' => $data['profitMargin'],
                    'recommendedPrice' => round($recommendedPrice, 2),
                    'currentProfit' => $product->price ? round((($product->price - $totalCostPerUnit) / $product->price) * 100, 2) : 0
                ]
            ];
            
            return response()->json([
                'success' => true,
                'data' => $results
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al calcular costos: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getPeriodMultiplier($period, $workingDays, $weeks)
    {
        switch ($period) {
            case 'lastMonth':
                return 1;
            case 'lastQuarter':
                return 3;
            case 'lastYear':
                return 12;
            case 'custom':
                return ($weeks * $workingDays) / 22; // 22 días laborales promedio por mes
            default:
                return 1;
        }
    }

    private function estimateProductSales($product, $period)
    {
        $userId = $product->user_id;
        
        // Calcular el rango de fechas según el período
        $endDate = now();
        $startDate = match($period) {
            'lastMonth' => now()->subMonth(),
            'lastQuarter' => now()->subMonths(3),
            'lastYear' => now()->subYear(),
            'custom' => now()->subMonth(), // Por defecto último mes
            default => now()->subMonth(),
        };
        
        // Buscar ventas del producto en el período
        // Usar created_at si sale_date es null, ya que en producción sale_date está null
        $sales = \App\Models\Sale::where('user_id', $userId)
            ->where('status', 'COMPLETED')
            ->where(function($query) use ($startDate, $endDate) {
                $query->whereBetween('sale_date', [$startDate, $endDate])
                      ->orWhere(function($subQuery) use ($startDate, $endDate) {
                          $subQuery->whereNull('sale_date')
                                   ->whereBetween('created_at', [$startDate, $endDate]);
                      });
            })
            ->get();
        
        $totalQuantity = 0;
        
        foreach ($sales as $sale) {
            $items = $sale->items;
            
            // Manejar tanto string JSON como array
            if (is_string($items)) {
                $items = json_decode($items, true);
            }
            
            if (is_array($items) && !empty($items)) {
                foreach ($items as $item) {
                    if (isset($item['productId']) && $item['productId'] == $product->id) {
                        $quantity = $item['quantity'] ?? 0;
                        
                        // Aplicar conversión de Pollo Completo a porciones individuales
                        if ($product->id == 24) { // ID del Pollo Completo
                            // No contar Pollo Completo directamente, se cuenta como porciones
                            continue;
                        }
                        
                        // Si es pechuga o pernil, agregar las cantidades del pollo completo convertidas
                        if ($product->id == 1) { // Porción De Pechuga
                            $totalQuantity += $quantity;
                            // Buscar pollos completos en esta misma venta y agregar 2 porciones por cada pollo
                            foreach ($items as $otherItem) {
                                if (isset($otherItem['productId']) && $otherItem['productId'] == 24) {
                                    $totalQuantity += ($otherItem['quantity'] ?? 0) * 2;
                                }
                            }
                        } elseif ($product->id == 2) { // Porción de Pernil  
                            $totalQuantity += $quantity;
                            // Buscar pollos completos en esta misma venta y agregar 2 porciones por cada pollo
                            foreach ($items as $otherItem) {
                                if (isset($otherItem['productId']) && $otherItem['productId'] == 24) {
                                    $totalQuantity += ($otherItem['quantity'] ?? 0) * 2;
                                }
                            }
                        } else {
                            $totalQuantity += $quantity;
                        }
                    }
                }
            }
        }
        
        \Log::info('Estimación de ventas calculada', [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'period' => $period,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'total_sales_found' => $sales->count(),
            'sales_with_items' => $sales->filter(function($sale) { return !empty($sale->items); })->count(),
            'total_quantity' => $totalQuantity
        ]);
        
        return $totalQuantity;
    }

    private function calculateWeightedFixedCost($product, $totalFixedCosts, $period)
    {
        $userId = $product->user_id;
        
        // Obtener todos los productos del usuario
        $allProducts = Product::where('user_id', $userId)->get();
        
        // Calcular ventas y pesos ponderados para todos los productos
        $productSalesData = [];
        $totalWeightedSales = 0;
        
        foreach ($allProducts as $prod) {
            $sales = $this->estimateProductSales($prod, $period);
            $weight = $prod->cost_weight ?? 1.0;
            $isMain = $prod->is_main_product ?? false;
            
            // Si es producto principal, usar peso completo
            // Si es producto secundario, reducir el peso considerablemente
            $adjustedWeight = $isMain ? $weight : ($weight * 0.1); // Productos secundarios solo 10% del peso
            
            $weightedSales = $sales * $adjustedWeight;
            $totalWeightedSales += $weightedSales;
            
            $productSalesData[] = [
                'product' => $prod,
                'sales' => $sales,
                'weight' => $weight,
                'adjusted_weight' => $adjustedWeight,
                'weighted_sales' => $weightedSales,
                'is_main' => $isMain
            ];
        }
        
        // Calcular el porcentaje que le corresponde al producto específico
        $currentProductData = collect($productSalesData)->firstWhere('product.id', $product->id);
        
        if (!$currentProductData || $totalWeightedSales == 0) {
            \Log::warning('No se pudieron calcular costos ponderados', [
                'product_id' => $product->id,
                'total_weighted_sales' => $totalWeightedSales
            ]);
            return 0;
        }
        
        $productPercentage = $currentProductData['weighted_sales'] / $totalWeightedSales;
        $fixedCostForProduct = $totalFixedCosts * $productPercentage;
        
        // Si el producto no tiene ventas, asignar un costo mínimo
        $productSales = $currentProductData['sales'];
        if ($productSales == 0) {
            \Log::info('Producto sin ventas - asignando costo mínimo', [
                'product_id' => $product->id,
                'product_name' => $product->name
            ]);
            return 500; // Costo fijo mínimo por unidad
        }
        
        $fixedCostPerUnit = $fixedCostForProduct / $productSales;
        
        \Log::info('Cálculo de costos fijos ponderado', [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'is_main_product' => $currentProductData['is_main'],
            'weight' => $currentProductData['weight'],
            'adjusted_weight' => $currentProductData['adjusted_weight'],
            'product_sales' => $productSales,
            'weighted_sales' => $currentProductData['weighted_sales'],
            'total_weighted_sales' => $totalWeightedSales,
            'product_percentage' => round($productPercentage * 100, 2) . '%',
            'fixed_cost_for_product' => $fixedCostForProduct,
            'fixed_cost_per_unit' => $fixedCostPerUnit,
            'main_products_in_system' => collect($productSalesData)->where('is_main', true)->count()
        ]);
        
        return $fixedCostPerUnit;
    }

    public function addStock(Request $request, $id)
    {
        $userId = $request->user()->id;
        $product = Product::where('user_id', $userId)->findOrFail($id);
        
        $request->validate([
            'quantity' => 'required|numeric|min:0',
            'reason' => 'required|string|max:255'
        ]);
        
        $previousStock = $product->stock;
        $product->increment('stock', $request->quantity);
        
        // Registrar el movimiento de stock
        \App\Models\StockMovement::create([
            'user_id' => $userId,
            'product_id' => $product->id,
            'type' => 'entrada',
            'quantity' => $request->quantity,
            'description' => $request->reason
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Stock agregado exitosamente',
            'data' => [
                'previous_stock' => $previousStock,
                'new_stock' => $product->stock,
                'quantity_added' => $request->quantity
            ]
        ]);
    }

    public function reduceStock(Request $request, $id)
    {
        $userId = $request->user()->id;
        $product = Product::where('user_id', $userId)->findOrFail($id);
        
        $request->validate([
            'quantity' => 'required|numeric|min:0',
            'reason' => 'required|string|max:255'
        ]);
        
        if ($product->stock < $request->quantity) {
            return response()->json([
                'success' => false,
                'message' => 'Stock insuficiente para reducir'
            ], 422);
        }
        
        $previousStock = $product->stock;
        $product->decrement('stock', $request->quantity);
        
        // Registrar el movimiento de stock
        \App\Models\StockMovement::create([
            'user_id' => $userId,
            'product_id' => $product->id,
            'type' => 'salida',
            'quantity' => $request->quantity,
            'description' => $request->reason
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Stock reducido exitosamente',
            'data' => [
                'previous_stock' => $previousStock,
                'new_stock' => $product->stock,
                'quantity_reduced' => $request->quantity
            ]
        ]);
    }

    public function stockMovement(Request $request, $id)
    {
        $userId = $request->user()->id;
        $product = Product::where('user_id', $userId)->findOrFail($id);
        
        $request->validate([
            'type' => 'required|in:in,out',
            'quantity' => 'required|numeric|min:0',
            'reason' => 'required|string|max:255'
        ]);
        
        $previousStock = $product->stock;
        $quantity = $request->quantity;
        
        if ($request->type === 'in') {
            $product->increment('stock', $quantity);
        } else {
            if ($product->stock < $quantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stock insuficiente para reducir'
                ], 422);
            }
            $product->decrement('stock', $quantity);
        }
        
        // Registrar el movimiento de stock
        \App\Models\StockMovement::create([
            'user_id' => $userId,
            'product_id' => $product->id,
            'type' => $request->type === 'in' ? 'entrada' : 'salida',
            'quantity' => $quantity,
            'description' => $request->reason
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Movimiento de stock registrado exitosamente',
            'data' => [
                'previous_stock' => $previousStock,
                'new_stock' => $product->stock,
                'movement_type' => $request->type,
                'quantity' => $quantity
            ]
        ]);
    }
}
