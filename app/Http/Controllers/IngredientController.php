<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Http\Resources\IngredientResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class IngredientController extends Controller
{
    /**
     * Obtener todos los ingredientes del usuario
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $ingredients = Ingredient::where('user_id', $userId)
            ->orderBy('name', 'asc')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => IngredientResource::collection($ingredients)
        ]);
    }

    /**
     * Obtener ingredientes con stock bajo
     */
    public function lowStock(Request $request)
    {
        $userId = $request->user()->id;
        $ingredients = Ingredient::where('user_id', $userId)
            ->whereRaw('stock <= min_stock')
            ->orderBy('stock', 'asc')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $ingredients
        ]);
    }

    /**
     * Crear un nuevo ingrediente
     */
    public function store(Request $request)
    {
        $userId = $request->user()->id;
        
        // Log para debugging
        \Log::info('Datos recibidos en store:', $request->all());
        
        // Validar datos de entrada
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'unit' => 'required|string|max:50',
            'quantityPurchased' => 'required|numeric|min:0',
            'purchaseValue' => 'required|numeric|min:0',
            'portionQuantity' => 'required|numeric|min:0',
            'portionUnit' => 'nullable|string|max:50',
            'stock' => 'nullable|integer|min:0',
            'minStock' => 'nullable|integer|min:0',
            'maxStock' => 'nullable|integer|min:0',
            'supplier' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'code' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            \Log::error('Errores de validación en store:', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only([
            'name', 'description', 'unit', 'quantityPurchased', 'purchaseValue',
            'portionQuantity', 'portionUnit', 'stock', 'minStock', 'maxStock',
            'supplier', 'location', 'code'
        ]);

        // Mapear campos del frontend a la base de datos
        $data['user_id'] = $userId;
        $data['quantity_purchased'] = $data['quantityPurchased'];
        $data['purchase_value'] = $data['purchaseValue'];
        $data['portion_quantity'] = $data['portionQuantity'];
        $data['portion_unit'] = $data['portionUnit'] ?? $data['unit'];
        $data['min_stock'] = $data['minStock'] ?? 0;
        $data['max_stock'] = $data['maxStock'] ?? null;

        // Calcular automáticamente el precio por unidad
        $data['price'] = $data['purchase_value'] / $data['quantity_purchased'];

        // Calcular automáticamente el costo por porción
        $data['portion_cost'] = $this->calculatePortionCost(
            $data['price'],
            $data['portion_quantity'],
            $data['unit'],
            $data['portion_unit']
        );

        // Calcular automáticamente el stock en porciones
        $data['stock'] = $this->calculateStockInPortions(
            $data['quantity_purchased'],
            $data['portion_quantity'],
            $data['unit'],
            $data['portion_unit']
        );

        // Log para debugging
        \Log::info('Datos del ingrediente a crear:', [
            'name' => $data['name'],
            'quantity_purchased' => $data['quantity_purchased'],
            'purchase_value' => $data['purchase_value'],
            'portion_quantity' => $data['portion_quantity'],
            'portion_unit' => $data['portion_unit'],
            'price' => $data['price'],
            'portion_cost' => $data['portion_cost'],
            'stock' => $data['stock']
        ]);

        // Limpiar campos del frontend
        unset($data['quantityPurchased'], $data['purchaseValue'], $data['portionQuantity'], 
              $data['portionUnit'], $data['minStock'], $data['maxStock']);

        try {
            $ingredient = Ingredient::create($data);
            
            return response()->json([
                'success' => true,
                'data' => new IngredientResource($ingredient)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear ingrediente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener un ingrediente específico
     */
    public function show(Request $request, $id)
    {
        $userId = $request->user()->id;
        $ingredient = Ingredient::where('user_id', $userId)->find($id);
        
        if (!$ingredient) {
            return response()->json([
                'success' => false,
                'message' => 'Ingrediente no encontrado'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => new IngredientResource($ingredient)
        ]);
    }

    /**
     * Actualizar un ingrediente
     */
    public function update(Request $request, $id)
    {
        $userId = $request->user()->id;
        $ingredient = Ingredient::where('user_id', $userId)->find($id);
        
        if (!$ingredient) {
            return response()->json([
                'success' => false,
                'message' => 'Ingrediente no encontrado'
            ], 404);
        }

        // Validar datos de entrada
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'unit' => 'sometimes|required|string|max:50',
            'quantityPurchased' => 'sometimes|required|numeric|min:0',
            'purchaseValue' => 'sometimes|required|numeric|min:0',
            'portionQuantity' => 'sometimes|required|numeric|min:0',
            'portionUnit' => 'nullable|string|max:50',
            'stock' => 'nullable|integer|min:0',
            'minStock' => 'nullable|integer|min:0',
            'maxStock' => 'nullable|integer|min:0',
            'supplier' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'code' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $data = $request->only([
            'name', 'description', 'unit', 'quantityPurchased', 'purchaseValue',
            'portionQuantity', 'portionUnit', 'stock', 'minStock', 'maxStock',
            'supplier', 'location', 'code'
        ]);

        // Mapear campos del frontend a la base de datos
        if (isset($data['quantityPurchased'])) {
            $data['quantity_purchased'] = $data['quantityPurchased'];
            unset($data['quantityPurchased']);
        }
        
        if (isset($data['purchaseValue'])) {
            $data['purchase_value'] = $data['purchaseValue'];
            unset($data['purchaseValue']);
        }
        
        if (isset($data['portionQuantity'])) {
            $data['portion_quantity'] = $data['portionQuantity'];
            unset($data['portionQuantity']);
        }
        
        if (isset($data['portionUnit'])) {
            $data['portion_unit'] = $data['portionUnit'];
            unset($data['portionUnit']);
        }
        
        if (isset($data['minStock'])) {
            $data['min_stock'] = $data['minStock'];
            unset($data['minStock']);
        }
        
        if (isset($data['maxStock'])) {
            $data['max_stock'] = $data['maxStock'];
            unset($data['maxStock']);
        }

        // Recalcular automáticamente si se actualizaron los valores base
        if (isset($data['quantity_purchased']) || isset($data['purchase_value'])) {
            $quantityPurchased = $data['quantity_purchased'] ?? $ingredient->quantity_purchased;
            $purchaseValue = $data['purchase_value'] ?? $ingredient->purchase_value;
            
            if ($quantityPurchased > 0 && $purchaseValue > 0) {
                $data['price'] = $purchaseValue / $quantityPurchased;
            }
        }

        // Recalcular costo por porción si se actualizó el precio o la porción
        if (isset($data['price']) || isset($data['portion_quantity']) || isset($data['unit']) || isset($data['portion_unit'])) {
            $price = $data['price'] ?? $ingredient->price;
            $portionQuantity = $data['portion_quantity'] ?? $ingredient->portion_quantity;
            $unit = $data['unit'] ?? $ingredient->unit;
            $portionUnit = $data['portion_unit'] ?? $ingredient->portion_unit;
            
            $data['portion_cost'] = $this->calculatePortionCost($price, $portionQuantity, $unit, $portionUnit);
        }

        // Recalcular stock en porciones si se actualizaron los valores base
        if (isset($data['quantity_purchased']) || isset($data['portion_quantity']) || isset($data['unit']) || isset($data['portion_unit'])) {
            $quantityPurchased = $data['quantity_purchased'] ?? $ingredient->quantity_purchased;
            $portionQuantity = $data['portion_quantity'] ?? $ingredient->portion_quantity;
            $unit = $data['unit'] ?? $ingredient->unit;
            $portionUnit = $data['portion_unit'] ?? $ingredient->portion_unit;
            
            $data['stock'] = $this->calculateStockInPortions($quantityPurchased, $portionQuantity, $unit, $portionUnit);
        }
        
        try {
            $ingredient->update($data);
            
            return response()->json([
                'success' => true,
                'data' => new IngredientResource($ingredient)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar ingrediente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un ingrediente
     */
    public function destroy(Request $request, $id)
    {
        $userId = $request->user()->id;
        $ingredient = Ingredient::where('user_id', $userId)->find($id);
        
        if (!$ingredient) {
            return response()->json([
                'success' => false,
                'message' => 'Ingrediente no encontrado'
            ], 404);
        }

        try {
            $ingredient->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Ingrediente eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar ingrediente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Agregar stock a un ingrediente
     */
    public function addStock(Request $request, $id)
    {
        $userId = $request->user()->id;
        $ingredient = Ingredient::where('user_id', $userId)->find($id);
        
        if (!$ingredient) {
            return response()->json([
                'success' => false,
                'message' => 'Ingrediente no encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'quantity' => 'required|numeric|min:0',
            'reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'unitCost' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $quantity = $request->quantity;
        $reason = $request->reason ?? 'STOCK_ADD';
        $notes = $request->notes;
        $unitCost = $request->unitCost ?? $ingredient->price;

        // Convertir la cantidad agregada a porciones
        $portionsToAdd = $this->convertToPortions($quantity, $ingredient->unit, $ingredient->portion_quantity, $ingredient->portion_unit);
        
        $previousStock = $ingredient->stock;
        $newStock = $previousStock + $portionsToAdd;

        try {
            $ingredient->update(['stock' => $newStock]);
            
            return response()->json([
                'success' => true,
                'data' => new IngredientResource($ingredient),
                'message' => "Stock agregado exitosamente. Nuevo stock: {$newStock} porciones"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al agregar stock: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reducir stock de un ingrediente
     */
    public function reduceStock(Request $request, $id)
    {
        $userId = $request->user()->id;
        $ingredient = Ingredient::where('user_id', $userId)->find($id);
        
        if (!$ingredient) {
            return response()->json([
                'success' => false,
                'message' => 'Ingrediente no encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'quantity' => 'required|numeric|min:0',
            'reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $quantity = $request->quantity;
        $reason = $request->reason ?? 'STOCK_REDUCE';
        $notes = $request->notes;

        // Convertir la cantidad a reducir a porciones
        $portionsToReduce = $this->convertToPortions($quantity, $ingredient->unit, $ingredient->portion_quantity, $ingredient->portion_unit);
        
        if ($ingredient->stock < $portionsToReduce) {
            return response()->json([
                'success' => false,
                'message' => "Stock insuficiente. Stock actual: {$ingredient->stock} porciones, cantidad solicitada: {$portionsToReduce} porciones"
            ], 400);
        }

        $previousStock = $ingredient->stock;
        $newStock = $previousStock - $portionsToReduce;

        try {
            $ingredient->update(['stock' => $newStock]);
            
            return response()->json([
                'success' => true,
                'data' => new IngredientResource($ingredient),
                'message' => "Stock reducido exitosamente. Nuevo stock: {$newStock} porciones"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al reducir stock: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calcular el costo por porción considerando conversión de unidades
     */
    private function calculatePortionCost($price, $portionQuantity, $unit, $portionUnit)
    {
        if ($price <= 0 || $portionQuantity <= 0) {
            return 0;
        }

        // Si las unidades son iguales, cálculo directo
        if ($unit === $portionUnit) {
            return $price * $portionQuantity;
        }

        // Convertir la porción a la unidad base para el cálculo
        $portionInBaseUnit = $this->convertToBaseUnit($portionQuantity, $portionUnit, $unit);
        
        return $price * $portionInBaseUnit;
    }

    /**
     * Calcular el stock en porciones
     */
    private function calculateStockInPortions($quantityPurchased, $portionQuantity, $unit, $portionUnit)
    {
        if ($quantityPurchased <= 0 || $portionQuantity <= 0) {
            return 0;
        }

        // Si las unidades son iguales, cálculo directo
        if ($unit === $portionUnit) {
            return (int) floor($quantityPurchased / $portionQuantity);
        }

        // Convertir la cantidad comprada a la unidad de la porción
        $quantityInPortionUnit = $this->convertToBaseUnit($quantityPurchased, $unit, $portionUnit);
        
        return (int) floor($quantityInPortionUnit / $portionQuantity);
    }

    /**
     * Convertir cantidad a porciones
     */
    private function convertToPortions($quantity, $fromUnit, $portionQuantity, $portionUnit)
    {
        if ($quantity <= 0 || $portionQuantity <= 0) {
            return 0;
        }

        // Convertir la cantidad a la unidad de la porción
        $quantityInPortionUnit = $this->convertToBaseUnit($quantity, $fromUnit, $portionUnit);
        
        return (int) floor($quantityInPortionUnit / $portionQuantity);
    }

    /**
     * Convertir entre unidades compatibles
     */
    private function convertToBaseUnit($quantity, $fromUnit, $toUnit)
    {
        // Si las unidades son iguales, no hay conversión
        if ($fromUnit === $toUnit) {
            return $quantity;
        }

        // Conversiones de peso
        if ($fromUnit === 'kg' && $toUnit === 'g') {
            return $quantity * 1000;
        }
        if ($fromUnit === 'g' && $toUnit === 'kg') {
            return $quantity / 1000;
        }

        // Conversiones de volumen
        if ($fromUnit === 'l' && $toUnit === 'ml') {
            return $quantity * 1000;
        }
        if ($fromUnit === 'ml' && $toUnit === 'l') {
            return $quantity / 1000;
        }

        // Para otras unidades, asumir que son compatibles
        return $quantity;
    }
}
